<?php
// load settings from settings.ini (page + settings sections)
// prefer project-root settings.ini (kept out of Docker image) but allow legacy src copy
$ini = [];
$paths = [
  __DIR__ . '/settings.ini',
  dirname(__DIR__, 2) . '/settings.ini'
];
foreach ($paths as $path) {
  if (file_exists($path)) {
    $ini = @parse_ini_file($path, true) ?: [];
    break;
  }
}
$page = $ini['page'] ?? [];
$cfg  = $ini['settings'] ?? [];

$pageTitle = $page['title'] ?? 'PHP Changelog (SQLite)';
$pageDesc  = $page['description'] ?? '';

$stylesheet = $cfg['stylesheet'] ?? 'styles.css';
$candidate = __DIR__ . '/assets/' . $stylesheet;
$stylesheetUrl = file_exists($candidate) ? '/assets/' . $stylesheet : '/assets/styles.css';

$companyName = $cfg['company_name'] ?? '';
$companyLogo = $cfg['company_logo'] ?? '';
$logoPath = __DIR__ . '/assets/' . $companyLogo;
$companyLogoUrl = ($companyLogo && file_exists($logoPath)) ? '/assets/' . $companyLogo : '';
$companyUrl = $cfg['company_url'] ?? '';
$contactEmail = $cfg['contact_email'] ?? '';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <?php if ($pageDesc): ?>
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?php echo $stylesheetUrl; ?>">
</head>
<body>
  <main class="container">
    <h1><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($pageDesc): ?>
      <p class="meta"><?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <section class="card">
      <h2>Submit entry</h2>
      <form id="entryForm">
        <label>Title <input name="title" required></label>
        <label>Description <textarea name="description" required></textarea></label>
        <label>Submitter <input name="submitter"></label>
        <label>Tags (comma separated) <input name="tags"></label>
        <div id="popularTags" class="popular-tags" aria-live="polite"></div>
        <button type="submit">Submit</button>
      </form>
    </section>

    <section class="card">
      <h2>Filter / Query</h2>
      <form id="filterForm">
        <label>From <input type="datetime-local" name="from"></label>
        <label>To <input type="datetime-local" name="to"></label>
        <label>Submitter <input name="submitter"></label>
        <label>Tags (comma separated) <input name="tags"></label>
        <div class="controls">
          <select name="sort">
            <option value="timestamp">Date</option>
            <option value="submitter">Submitter</option>
            <option value="tags">Tags</option>
            <option value="title">Title</option>
          </select>
          <select name="order">
            <option value="desc">Newest</option>
            <option value="asc">Oldest</option>
          </select>
          <button type="submit">Apply</button>
          <button type="button" id="clearFilters">Clear</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Entries</h2>
      <div id="entries"></div>
    </section>
  </main>

  <?php if ($companyName || $companyLogoUrl || $contactEmail): ?>
    <footer class="container">
      <div class="card meta" style="display:flex;gap:12px;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:12px;align-items:center">
          <?php if ($companyLogoUrl): ?>
            <img src="<?php echo $companyLogoUrl; ?>" alt="<?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" style="height:32px;">
          <?php endif; ?>
          <?php if ($companyUrl && $companyName): ?>
            <a href="<?php echo htmlspecialchars($companyUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></a>
          <?php elseif ($companyName): ?>
            <span><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </div>
        <?php if ($contactEmail): ?>
          <div><a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a></div>
        <?php endif; ?>
      </div>
    </footer>
  <?php endif; ?>

  <script src="/assets/app.js"></script>
</body>
</html>
