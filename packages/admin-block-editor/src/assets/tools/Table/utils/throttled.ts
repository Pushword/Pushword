
/**
 * Limits the frequency of calling a function
 *
 * @param {number} delay - delay between calls in milliseconds
 * @param {function} fn - function to be throttled
 */
export default function throttled(delay: number, fn: (...args: any[]) => any): (...args: any[]) => any {
  let lastCall = 0;

  return function (...args: any[]) {
    const now = new Date().getTime();

    if (now - lastCall < delay) {
      return;
    }

    lastCall = now;

    return fn(...args);
  };
}
