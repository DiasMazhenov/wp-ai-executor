const { test } = require('node:test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

test('PHP generation preserves provider design, mobile overrides and later edits', () => {
    const result = execFileSync('php', [path.join(__dirname, 'flex-generation-runtime.php')], { encoding: 'utf8', timeout: 20000 });
    process.stdout.write(result);
});
