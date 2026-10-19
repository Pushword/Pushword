param([Parameter(Mandatory = $true)][int]$BrowserPid)
$ErrorActionPreference = 'Stop'

# Send physical AZERTY scan codes in the interactive desktop, including dead keys.
Add-Type @'
using System;
using System.Runtime.InteropServices;
public static class EditorKeyboard {
  [StructLayout(LayoutKind.Sequential)] public struct KEYBDINPUT {
    public ushort vk, scan;
    public uint flags, time;
    public UIntPtr extra;
  }
  [StructLayout(LayoutKind.Sequential)] public struct MOUSEINPUT {
    public int x, y;
    public uint data, flags, time;
    public UIntPtr extra;
  }
  [StructLayout(LayoutKind.Explicit)] public struct UNION {
    [FieldOffset(0)] public KEYBDINPUT keyboard;
    [FieldOffset(0)] public MOUSEINPUT mouse;
  }
  [StructLayout(LayoutKind.Sequential)] public struct INPUT {
    public uint type;
    public UNION data;
  }
  [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr window);
  [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
  [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr window, out uint process);
  [DllImport("user32.dll")] public static extern IntPtr LoadKeyboardLayout(string layout, uint flags);
  [DllImport("user32.dll")] public static extern IntPtr GetKeyboardLayout(uint thread);
  [DllImport("user32.dll")] public static extern bool PostMessage(IntPtr window, uint message, IntPtr wparam, IntPtr lparam);
  [DllImport("user32.dll", SetLastError = true)] static extern uint SendInput(uint count, INPUT[] inputs, int size);
  public static void Key(ushort scan, bool up) {
    var input = new INPUT { type = 1 };
    input.data.keyboard.scan = scan;
    input.data.keyboard.flags = (uint)(8 | (up ? 2 : 0));
    if (SendInput(1, new INPUT[] { input }, Marshal.SizeOf(typeof(INPUT))) != 1)
      throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error());
  }
}
'@

$browser = Get-Process -Id $BrowserPid
$window = $browser.MainWindowHandle
if ($window -eq [IntPtr]::Zero) { throw 'The test Edge process has no desktop window' }
$threadProcess = [uint32]0
$thread = [EditorKeyboard]::GetWindowThreadProcessId($window, [ref]$threadProcess)
$previous = [EditorKeyboard]::GetKeyboardLayout($thread)
$french = [EditorKeyboard]::LoadKeyboardLayout('0000040c', 0)
if ($french -eq [IntPtr]::Zero) { throw 'French AZERTY keyboard layout is unavailable' }
try {
  [void][EditorKeyboard]::SetForegroundWindow($window)
  if ([EditorKeyboard]::GetForegroundWindow() -ne $window) { throw 'The test Edge window is not in the foreground' }
  [void][EditorKeyboard]::PostMessage($window, 0x50, [IntPtr]::Zero, $french)
  Start-Sleep -Milliseconds 250
  # Physical 2 (é), space, circumflex + e (ê), space, Shift + circumflex + u (ü).
  foreach ($scan in @(0x03, 0x39, 0x1a, 0x12, 0x39)) {
    [EditorKeyboard]::Key($scan, $false)
    [EditorKeyboard]::Key($scan, $true)
    Start-Sleep -Milliseconds 60
  }
  [EditorKeyboard]::Key(0x2a, $false)
  [EditorKeyboard]::Key(0x1a, $false)
  [EditorKeyboard]::Key(0x1a, $true)
  [EditorKeyboard]::Key(0x2a, $true)
  [EditorKeyboard]::Key(0x16, $false)
  [EditorKeyboard]::Key(0x16, $true)
  Start-Sleep -Milliseconds 100
} finally {
  [void][EditorKeyboard]::PostMessage($window, 0x50, [IntPtr]::Zero, $previous)
}
