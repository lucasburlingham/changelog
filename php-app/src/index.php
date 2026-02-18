<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>PHP Changelog (SQLite)</title>
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
  <main class="container">
    <h1>Changelog (PHP + SQLite)</h1>

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

  <script src="/assets/app.js"></script>
</body>
</html>
