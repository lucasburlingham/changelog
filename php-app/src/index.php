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

// Fallback .env loading for non-Docker Apache deployments.
$dotenv = [];
$dotenvPaths = [
  dirname(__DIR__, 2) . '/.env',
  dirname(__DIR__) . '/.env',
  __DIR__ . '/.env'
];
foreach ($dotenvPaths as $dotenvPath) {
  if (!is_file($dotenvPath)) {
    continue;
  }

  $lines = @file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) {
    continue;
  }

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
      continue;
    }

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    if ($k === '' || isset($dotenv[$k])) {
      continue;
    }

    $v = trim($v);
    $dotenv[$k] = trim($v, "\"' ");
  }

  break;
}

function env_value(string $key, string $fallback = ''): string {
  global $dotenv;

  $v = getenv($key);
  if ($v !== false && $v !== '') {
    return (string)$v;
  }

  if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
    return (string)$_SERVER[$key];
  }

  if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
    return (string)$_ENV[$key];
  }

  if (isset($dotenv[$key]) && $dotenv[$key] !== '') {
    return (string)$dotenv[$key];
  }

  return $fallback;
}

// prefer environment variables (set via docker-compose/.env); fall back to settings.ini values
$pageTitle = env_value('PAGE_TITLE', $page['title'] ?? 'PHP Changelog');
$pageDesc  = env_value('PAGE_DESCRIPTION', $page['description'] ?? '');

$stylesheet = env_value('STYLESHEET', $cfg['stylesheet'] ?? 'styles.css');
$candidate = __DIR__ . '/assets/' . $stylesheet;
$stylesheetUrl = file_exists($candidate) ? '/assets/' . $stylesheet : '/assets/styles.css';

$companyName = env_value('COMPANY_NAME', $cfg['company_name'] ?? '');
$companyLogo = env_value('COMPANY_LOGO', $cfg['company_logo'] ?? '');
$logoPath = __DIR__ . '/assets/' . $companyLogo;
$companyLogoUrl = ($companyLogo && file_exists($logoPath)) ? '/assets/' . $companyLogo : '';
$companyUrl = env_value('COMPANY_URL', $cfg['company_url'] ?? '');
$contactEmail = env_value('CONTACT_EMAIL', $cfg['contact_email'] ?? '');
$tinyMceApiKey = trim(env_value('TINYMCE_API_KEY', ''));
$tinyMceApiKey = trim($tinyMceApiKey, "\"' ");
if ($tinyMceApiKey === '') {
  $tinyMceApiKey = 'no-api-key';
}
$tinyMceUrl = 'https://cdn.tiny.cloud/1/' . rawurlencode($tinyMceApiKey) . '/tinymce/8/tinymce.min.js';
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
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <h1><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($pageDesc): ?>
          <p class="meta"><?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
      <button id="darkModeToggle" aria-label="Toggle dark mode" style="background:none;border:none;font-size:24px;cursor:pointer;padding:8px;border-radius:6px;transition:background 0.2s" title="Toggle dark mode">☀️</button>
    </div>

    <div class="layout-grid">
      <div class="left-column">
        <section class="card">
          <h2>Submit entry</h2>
          <form id="entryForm">
            <label>Title <input name="title" required></label>
            <label>Description <textarea id="entryDescription" name="description" required></textarea></label>
            <label>Submitter <input name="submitter"></label>
            <div id="popularSubmittersEntry" class="popular-submitters" aria-live="polite"></div>
            <label>Tags (comma separated) <input name="tags"></label>
            <div id="popularTags" class="popular-tags" aria-live="polite"></div>
            <button type="submit">Submit</button>
          </form>
        </section>
      </div>

      <div class="right-column">
        <section class="card">
          <h2>Entries</h2>
          <div id="entries"></div>
        </section>
      </div>

      <div class="filter-column">
        <section class="card">
          <h2>Filter / Query</h2>
          <form id="filterForm">
            <label>From <input type="datetime-local" name="from"></label>
            <label>To <input type="datetime-local" name="to"></label>
            <label>Submitter <input name="submitter"></label>
            <div id="popularSubmittersFilter" class="popular-submitters" aria-live="polite"></div>
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
      </div>
    </div>
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

  <script src="<?php echo htmlspecialchars($tinyMceUrl, ENT_QUOTES, 'UTF-8'); ?>" referrerpolicy="origin" crossorigin="anonymous"></script>
  <script src="/assets/app.js"></script>
</body>
</html>
