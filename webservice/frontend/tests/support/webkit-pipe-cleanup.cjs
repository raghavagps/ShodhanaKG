// Test-runner-only workaround for Windows WebKit descendants retaining stdio.
// A clean process exit is required; assertions and browser errors are untouched.
const childProcess = require('node:child_process');

function watchWebKitExit(child, command, platform = process.platform, warn = console.error) {
  if (platform !== 'win32' || !/[\\/]webkit-\d+[\\/]Playwright\.exe$/i.test(String(command))) return;
  let closed = false;
  let timer;
  child.once('close', () => { closed = true; clearTimeout(timer); });
  child.once('exit', (code, signal) => {
    if (code !== 0 || signal || closed) return;
    // Allow buffered shutdown messages to drain before releasing inherited pipes.
    timer = setTimeout(() => {
      if (closed) return;
      warn(`[test teardown] WebKit process ${child.pid} exited 0; releasing inherited stdio pipes.`);
      for (const pipe of child.stdio || []) {
        if (pipe && !pipe.destroyed && typeof pipe.destroy === 'function') pipe.destroy();
      }
    }, 1000);
    timer.unref?.();
  });
}

if (process.env.SHODHANA_WEBKIT_PIPE_CLEANUP === '1' && process.platform === 'win32') {
  const spawn = childProcess.spawn;
  childProcess.spawn = function (command, ...args) {
    const child = spawn.call(this, command, ...args);
    watchWebKitExit(child, command);
    return child;
  };
}

module.exports = { watchWebKitExit };
