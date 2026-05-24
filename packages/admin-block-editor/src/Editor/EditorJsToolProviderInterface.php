<?php

namespace Pushword\AdminBlockEditor\Editor;

/**
 * Lets other bundles contribute EditorJS tool configurations to the block editor
 * without the editor knowing about them. Implementations are autoconfigured with
 * the `pushword.editorjs_tool_provider` tag and merged into `editorjsConfig.tools`
 * by the editor widget template.
 */
interface EditorJsToolProviderInterface
{
    /**
     * @return array<string, array<string, mixed>> map of tool name => EditorJS tool config.
     *                                             The `className` must match a tool exposed in editorjsTools.
     */
    public function getToolsConfig(string $host): array;
}
