const { test } = require('node:test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

test('PHP generation preserves provider design, mobile overrides and later edits', () => {
    // This integration harness exercises the complete typed/native pipeline;
    // keep enough headroom for slower local PHP builds under Node's runner.
    const result = execFileSync('php', [path.join(__dirname, 'flex-generation-runtime.php')], { encoding: 'utf8', timeout: 60000 });
    process.stdout.write(result);
});
