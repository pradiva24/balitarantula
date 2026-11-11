<?php
require __DIR__.'/includes/auth.php';
$page_title='Permalinks';
require __DIR__.'/includes/header.php';
?>
<div class="section">
  <div class="head">
    <div>
      <h2>Permalink Settings</h2>
      <div class="small">Clean public URLs, similar to WordPress-style routing.</div>
    </div>
  </div>

  <div class="tablebox">
    <table class="table">
      <tr><th>Content</th><th>Public URL</th><th>Internal File</th></tr>
      <tr><td>Pages</td><td><code>/page/{slug}</code></td><td><code>page.php</code></td></tr>
      <tr><td>Packages</td><td><code>/package/{slug}</code></td><td><code>package.php</code></td></tr>
      <tr><td>Blog Articles</td><td><code>/blog/{slug}</code></td><td><code>blog-detail.php</code></td></tr>
      <tr><td>Packages Listing</td><td><code>/packages</code></td><td><code>packages.php</code></td></tr>
      <tr><td>Blog Listing</td><td><code>/blog</code></td><td><code>blog.php</code></td></tr>
    </table>
  </div>
</div>

<div class="section card">
  <h3>Page Example</h3>
  <p class="small">A page with slug <b>privacy-policy</b> is now available at:</p>
  <p><code><?=admin_e(BASE_URL)?>/page/privacy-policy</code></p>
  <p class="small">The PHP file remains <code>page.php</code>; visitors use the clean permalink.</p>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
