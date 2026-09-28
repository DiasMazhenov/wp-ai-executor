const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const directory = path.join(root, 'includes/elementor/imported-templates');
const manifest = JSON.parse(fs.readFileSync(path.join(directory, 'manifest.json'), 'utf8'));
const editorLibrary = fs.readFileSync(path.join(root, 'assets/js/elementor-block-library-ui.js'), 'utf8');

test('bundled Elementor template imports are complete and hash verified', () => {
  assert.equal(manifest.format, 'wpae-imported-elementor-templates-v1');
  assert.equal(manifest.files.length, 158);
  assert.equal(new Set(manifest.files.map((item) => item.id)).size, 158);
  assert.equal(new Set(manifest.files.map((item) => item.file)).size, 158);

  let treeCount = 0;
  let proCount = 0;
  for (const item of manifest.files) {
    assert.match(item.file, /^[a-z0-9][a-z0-9_-]{0,119}\.json$/);
    const bytes = fs.readFileSync(path.join(directory, item.file));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), item.sha256, item.file);
    const source = JSON.parse(bytes.toString('utf8'));
    assert.equal(item.has_content, Array.isArray(source.content) && source.content.length > 0, item.file);
    if (item.has_content) treeCount++;
    if (item.elementor_pro_required) proCount++;
  }
  assert.equal(treeCount, 156);
  assert.equal(proCount, 76);
});

test('editor inserts imported templates through Elementor native create command', () => {
  assert.match(editorLibrary, /document\/elements\/create/);
  assert.match(editorLibrary, /getPreviewContainer/);
  assert.doesNotMatch(editorLibrary, /document\/ui\/paste/);
});
