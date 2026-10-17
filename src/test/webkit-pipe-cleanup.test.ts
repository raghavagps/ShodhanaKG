import { EventEmitter } from 'node:events';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { watchWebKitExit } from '../../tests/support/webkit-pipe-cleanup.cjs';

const executable = 'C:\\cache\\webkit-2287\\Playwright.exe';
function child() {
  return Object.assign(new EventEmitter(), { pid: 42, stdio: [{ destroy: vi.fn(), destroyed: false }, null] });
}
afterEach(() => vi.useRealTimers());
describe('Windows WebKit test teardown', () => {
  it('releases inherited pipes only after a clean browser exit and drain delay', () => {
    vi.useFakeTimers();
    const process = child(); const warn = vi.fn();
    watchWebKitExit(process, executable, 'win32', warn);
    vi.advanceTimersByTime(2000);
    expect(process.stdio[0].destroy).not.toHaveBeenCalled();
    process.emit('exit', 0, null);
    vi.advanceTimersByTime(999);
    expect(process.stdio[0].destroy).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(process.stdio[0].destroy).toHaveBeenCalledOnce();
    expect(warn).toHaveBeenCalledOnce();
  });
  it.each([[1, null], [null, 'SIGTERM']])('does not hide failed exits: %s %s', (code, signal) => {
    vi.useFakeTimers(); const process = child();
    watchWebKitExit(process, executable, 'win32', vi.fn());
    process.emit('exit', code, signal); vi.advanceTimersByTime(2000);
    expect(process.stdio[0].destroy).not.toHaveBeenCalled();
  });
  it('leaves normal close events and already-destroyed streams alone', () => {
    vi.useFakeTimers(); const process = child();
    watchWebKitExit(process, executable, 'win32', vi.fn());
    process.emit('exit', 0, null); process.emit('close', 0, null);
    vi.advanceTimersByTime(2000);
    expect(process.stdio[0].destroy).not.toHaveBeenCalled();
    const ended = child(); ended.stdio[0].destroyed = true;
    watchWebKitExit(ended, executable, 'win32', vi.fn());
    ended.emit('exit', 0, null); vi.advanceTimersByTime(2000);
    expect(ended.stdio[0].destroy).not.toHaveBeenCalled();
  });
  it.each([[executable, 'linux'], ['C:\\node.exe', 'win32'], ['C:\\Playwright.exe', 'win32']])('ignores unrelated processes: %s %s', (command, platform) => {
    vi.useFakeTimers(); const process = child();
    watchWebKitExit(process, command, platform, vi.fn());
    process.emit('exit', 0, null); vi.advanceTimersByTime(2000);
    expect(process.stdio[0].destroy).not.toHaveBeenCalled();
  });
});
