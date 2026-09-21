const { test } = require('node:test');
const { spawnSync } = require('node:child_process');

test('design pipeline PHP contracts', () => {
  const result = spawnSync('php', ['tests/design-pipeline-contract.php'], { encoding: 'utf8' });
  if (result.status !== 0) {
    throw new Error(`${result.stdout}\n${result.stderr}`);
  }
  if (!result.stdout.includes('design pipeline contract:')) {
    throw new Error(`Unexpected output: ${result.stdout}`);
  }
});
