// Dark mode toggle
function initDarkMode(){
  const toggle = document.getElementById('darkModeToggle');
  const isDarkMode = localStorage.getItem('darkMode') === 'true' || window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  function setDarkMode(isDark){
    if(isDark){
      document.documentElement.classList.add('dark-mode');
      localStorage.setItem('darkMode', 'true');
      toggle.textContent = '🌙';
    } else {
      document.documentElement.classList.remove('dark-mode');
      localStorage.setItem('darkMode', 'false');
      toggle.textContent = '☀️';
    }
  }
  
  setDarkMode(isDarkMode);
  toggle.addEventListener('click', ()=> setDarkMode(!document.documentElement.classList.contains('dark-mode')));
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', initDarkMode);
} else {
  initDarkMode();
}

async function apiFetch(path, opts={}){
  const res = await fetch(path, Object.assign({headers:{'Content-Type':'application/json'}}, opts));
  if(!res.ok) throw new Error(await res.text());
  return res.json();
}

function readForm(form){
  const fd = new FormData(form);
  const out = {};
  for(const [k,v] of fd.entries()) out[k] = v;
  return out;
}

function getDescriptionEditor(){
  return window.tinymce ? window.tinymce.get('entryDescription') : null;
}

async function initDescriptionEditor(){
  const textarea = document.getElementById('entryDescription');
  if(!textarea || !window.tinymce) return null;

  const existingEditor = getDescriptionEditor();
  if(existingEditor) return existingEditor;

  const editors = await window.tinymce.init({
    selector: '#entryDescription',
    height: 320,
    menubar: false,
    branding: false,
    promotion: false,
    browser_spellcheck: true,
    plugins: [
      'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount'
    ],
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
    content_style: 'body { font-family: Segoe UI, Roboto, Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
    setup(editor){
      const syncEditor = ()=> editor.save();
      editor.on('change input undo redo setcontent', syncEditor);
    }
  });

  return editors[0] || null;
}

function sanitizeUrl(value){
  const rawValue = String(value || '').trim();
  if(!rawValue) return '';
  if(rawValue.startsWith('#') || rawValue.startsWith('/')) return rawValue;

  try{
    const url = new URL(rawValue, window.location.origin);
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol) ? url.href : '';
  }catch{
    return '';
  }
}

function sanitizeImageSrc(value){
  const rawValue = String(value || '').trim();
  if(!rawValue) return '';

  // Allow data URLs for common raster image types only.
  if(/^data:image\/(png|jpe?g|gif|webp|bmp|avif);base64,[a-z0-9+/=\s]+$/i.test(rawValue)){
    return rawValue.replace(/\s+/g, '');
  }

  // TinyMCE can emit blob URLs for local inserts.
  if(rawValue.startsWith('blob:')){
    try{
      const url = new URL(rawValue);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    }catch{
      return '';
    }
  }

  return sanitizeUrl(rawValue);
}

function blobToDataUrl(blob){
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = ()=> resolve(String(reader.result || ''));
    reader.onerror = ()=> reject(new Error('Failed to convert blob image to data URL.'));
    reader.readAsDataURL(blob);
  });
}

async function normalizeImageSources(html){
  const parser = new DOMParser();
  const doc = parser.parseFromString(`<div>${html || ''}</div>`, 'text/html');
  const sourceRoot = doc.body.firstElementChild || doc.body;
  const images = Array.from(sourceRoot.querySelectorAll('img[src^="blob:"]'));

  for(const img of images){
    const src = img.getAttribute('src') || '';
    try{
      const res = await fetch(src);
      if(!res.ok) continue;
      const dataUrl = await blobToDataUrl(await res.blob());
      if(dataUrl) img.setAttribute('src', dataUrl);
    }catch{
      // If conversion fails, keep the original source and let sanitizer handle it.
    }
  }

  return sourceRoot.innerHTML;
}

function sanitizeRichTextHtml(html){
  const parser = new DOMParser();
  const doc = parser.parseFromString(`<div>${html || ''}</div>`, 'text/html');
  const sourceRoot = doc.body.firstElementChild || doc.body;
  const safeDoc = document.implementation.createHTMLDocument('sanitized');
  const safeRoot = safeDoc.createElement('div');
  const allowedTags = new Set(['a', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4', 'hr', 'img', 'li', 'ol', 'p', 'pre', 's', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul']);

  function sanitizeNode(node, parent){
    if(node.nodeType === Node.TEXT_NODE){
      parent.appendChild(safeDoc.createTextNode(node.textContent || ''));
      return;
    }

    if(node.nodeType !== Node.ELEMENT_NODE) return;

    const tagName = node.tagName.toLowerCase();
    if(!allowedTags.has(tagName)){
      Array.from(node.childNodes).forEach(child => sanitizeNode(child, parent));
      return;
    }

    const safeElement = safeDoc.createElement(tagName);
    if(tagName === 'a'){
      const href = sanitizeUrl(node.getAttribute('href'));
      if(href) safeElement.setAttribute('href', href);
      if(node.getAttribute('target') === '_blank'){
        safeElement.setAttribute('target', '_blank');
        safeElement.setAttribute('rel', 'noopener noreferrer');
      }
    }

    if(tagName === 'img'){
      const src = sanitizeImageSrc(node.getAttribute('src'));
      if(!src) return;
      safeElement.setAttribute('src', src);

      const alt = (node.getAttribute('alt') || '').trim();
      if(alt) safeElement.setAttribute('alt', alt.slice(0, 300));

      const width = node.getAttribute('width');
      if(width && /^\d+$/.test(width)) safeElement.setAttribute('width', width);

      const height = node.getAttribute('height');
      if(height && /^\d+$/.test(height)) safeElement.setAttribute('height', height);

      const title = (node.getAttribute('title') || '').trim();
      if(title) safeElement.setAttribute('title', title.slice(0, 300));

      const loading = (node.getAttribute('loading') || '').toLowerCase();
      if(loading === 'lazy' || loading === 'eager') safeElement.setAttribute('loading', loading);

      parent.appendChild(safeElement);
      return;
    }

    if(tagName === 'td' || tagName === 'th'){
      ['colspan', 'rowspan'].forEach(attr => {
        const value = node.getAttribute(attr);
        if(value && /^\d+$/.test(value)) safeElement.setAttribute(attr, value);
      });
    }

    if(tagName === 'ol'){
      const start = node.getAttribute('start');
      if(start && /^\d+$/.test(start)) safeElement.setAttribute('start', start);
    }

    Array.from(node.childNodes).forEach(child => sanitizeNode(child, safeElement));
    parent.appendChild(safeElement);
  }

  Array.from(sourceRoot.childNodes).forEach(child => sanitizeNode(child, safeRoot));
  return safeRoot.innerHTML.trim();
}

function isRichTextBlank(html){
  if(!html) return true;
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  const text = (doc.body.textContent || '').replace(/\u00a0/g, ' ').trim();
  return text === '' && !doc.body.querySelector('hr, table');
}

function clearDescriptionEditor(){
  const editor = getDescriptionEditor();
  if(!editor) return;
  editor.setContent('');
  editor.save();
}

function focusDescriptionEditor(){
  const editor = getDescriptionEditor();
  if(editor){
    editor.focus();
    return;
  }

  const textarea = document.getElementById('entryDescription');
  if(textarea) textarea.focus();
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]||c)); }
// Choose readable text color (black or white) for a hex color (expects "rrggbb" or "#rrggbb")
function pickTextColor(hex){
  if(!hex) return 'var(--accent)';
  const h = String(hex).replace(/^#/, '');
  if(h.length !== 6) return 'var(--accent)';
  const r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
  const yiq = (r*299 + g*587 + b*114) / 1000; // simple luminance
  return yiq >= 128 ? '#000' : '#fff';
}

// Return true if background hex is considered "very light" (use to add subtle shadow/border)
function isVeryLight(hex){
  if(!hex) return false;
  const h = String(hex).replace(/^#/, '');
  if(h.length !== 6) return false;
  const r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
  const lum = (r*299 + g*587 + b*114) / 1000;
  return lum > 220;
}

// Return hex string for a known tag from `tagSuggestions` (or empty string)
function hexForTag(tag){ const t = tagSuggestions.find(x=> x.tag === tag); return t ? (t.hex || '') : ''; }
// Tag suggestions (populated from /api/tags.php)
let tagSuggestions = [];
async function loadTagSuggestions(){
  try{ const res = await fetch('/api/tags.php'); tagSuggestions = await res.json(); }catch(e){ tagSuggestions = []; }
}

// Submitter suggestions (populated from /api/submitters.php)
let submitterSuggestions = [];
async function loadSubmitterSuggestions(){
  try{ const res = await fetch('/api/submitters.php'); submitterSuggestions = await res.json(); }catch(e){ submitterSuggestions = []; }
}

function attachTagSuggestor(input){
  const wrapper = document.createElement('div');
  input.parentNode.insertBefore(wrapper, input.nextSibling);
  const list = document.createElement('div'); list.className='tag-suggestions-list'; wrapper.appendChild(list);
  let selected = -1;

  function currentToken(){
    const parts = input.value.split(',');
    const last = parts.pop();
    const prefix = parts.length ? parts.join(',') + ',' : '';
    return {raw: last || '', token: String(last||'').trim(), prefix};
  }

  function renderMatches(){
    const {token} = currentToken();
    const q = token.trim().toLowerCase();
    const matches = tagSuggestions.filter(t=> q === '' ? true : t.tag.toLowerCase().includes(q)).slice(0,8);
    if(!matches.length){ list.innerHTML=''; selected=-1; return; }
    list.innerHTML = matches.map(m=>`<div class="tag-suggestion" data-tag="${escapeHtml(m.tag)}" data-hex="${escapeHtml(m.hex||'')}"><span class="swatch" style="background:#${escapeHtml(m.hex||'')}"></span><span class="label">${escapeHtml(m.tag)}</span></div>`).join('');
    Array.from(list.children).forEach((el, idx)=>{
      el.addEventListener('mousedown', ev=>{ ev.preventDefault(); choose(idx); });
    });
    selected = -1;
  }

  function choose(idx){
    const el = list.children[idx];
    if(!el) return;
    const tag = el.dataset.tag;
    const {prefix} = currentToken();
    input.value = (prefix + (prefix ? ' ' : '') + tag).replace(/\s+,/g, ',').trim();
    if(!input.value.endsWith(',')) input.value = input.value + ', ';
    input.focus();
    list.innerHTML = '';
    selected = -1;
  }

  input.addEventListener('input', ()=>{ renderMatches(); });
  input.addEventListener('keydown', e=>{
    const items = list.children;
    if(!items.length) return;
    if(e.key === 'ArrowDown'){ e.preventDefault(); selected = Math.min(selected+1, items.length-1); updateSelected(); }
    else if(e.key === 'ArrowUp'){ e.preventDefault(); selected = Math.max(selected-1, 0); updateSelected(); }
    else if(e.key === 'Enter' || e.key === 'Tab'){ if(selected >= 0){ e.preventDefault(); choose(selected); } }
  });
  input.addEventListener('blur', ()=> setTimeout(()=>{ list.innerHTML=''; selected=-1; }, 150));

  function updateSelected(){ Array.from(list.children).forEach((el, i)=> el.classList.toggle('selected', i===selected)); }
}

function addTagToInput(input, tag){
  const parts = input.value.split(',').map(s=>s.trim()).filter(Boolean);
  if(parts.includes(tag)) return;
  parts.push(tag);
  input.value = parts.join(', ') + (parts.length ? ', ' : '');
  input.focus();
}

function renderPopularTags(){
  const container = document.getElementById('popularTags');
  if(!container) return;
  const used = tagSuggestions.filter(t=> (t.count||0) > 0).sort((a,b)=> (b.count||0) - (a.count||0) || a.tag.localeCompare(b.tag));
  if(!used.length){ container.innerHTML=''; return; }
  container.innerHTML = used.map(t=>{
    const hex = t.hex || '';
    const bg = hex ? ('#' + escapeHtml(hex)) : '';
    const color = hex ? pickTextColor(hex) : 'var(--accent)';
    const borderStyle = hex ? 'border:1px solid rgba(0,0,0,0.06);' : '';
    const lightClass = (hex && isVeryLight(hex)) ? ' light' : '';
    return `<button type="button" class="popular-tag${lightClass}" data-tag="${escapeHtml(t.tag)}" style="background:${bg}; color:${color}; ${borderStyle}"><span class="label">${escapeHtml(t.tag)}</span><span class="count">${t.count||0}</span></button>`;
  }).join('');
  container.querySelectorAll('.popular-tag').forEach(btn=>{
    btn.addEventListener('click', ()=> addTagToInput(document.querySelector('#entryForm input[name="tags"]'), btn.dataset.tag));
  });
}

function addSubmitterToInput(input, submitter){
  input.value = submitter;
  input.focus();
}

function renderPopularSubmitters(containerId, formInputSelector){
  const container = document.getElementById(containerId);
  if(!container) return;
  const used = submitterSuggestions.filter(s=> (s.count||0) > 0).sort((a,b)=> (b.count||0) - (a.count||0) || a.submitter.localeCompare(b.submitter));
  if(!used.length){ container.innerHTML=''; return; }
  container.innerHTML = used.map(s=>{
    return `<button type="button" class="popular-submitter" data-submitter="${escapeHtml(s.submitter)}"><span class="label">${escapeHtml(s.submitter)}</span><span class="count">${s.count||0}</span></button>`;
  }).join('');
  container.querySelectorAll('.popular-submitter').forEach(btn=>{
    btn.addEventListener('click', (e)=>{ 
      e.preventDefault(); 
      addSubmitterToInput(document.querySelector(formInputSelector), btn.dataset.submitter); 
    });
  });
}

const ENTRIES_PAGE_SIZE = 50;
let entriesOffset = 0;
let loadingEntries = false;
let hasMoreEntries = true;
let activeEntryFilters = {};
let entriesQueryVersion = 0;

function renderEntries(list, append=false){
  const container = document.getElementById('entries');
  if(!append) container.innerHTML = '';
  if(!append && !list.length) { container.innerHTML = '<p>No entries</p>'; return; }
  list.forEach(e=>{
    const div = document.createElement('div'); div.className='entry';
    const title = document.createElement('div'); title.innerHTML = `<strong>${escapeHtml(e.title)}</strong>`;
    const meta = document.createElement('div'); meta.className='meta';
    const d = new Date(e.timestamp);
    meta.textContent = `${d.toLocaleString()} — ${e.submitter||'—'}`;
    const desc = document.createElement('div'); desc.className = 'description'; desc.innerHTML = sanitizeRichTextHtml(e.description);
    const tags = document.createElement('div'); tags.className='tags';
    (e.tags||[]).forEach(tagName=>{
      const s = document.createElement('span'); s.className = 'tag'; s.textContent = tagName;
      const hex = hexForTag(tagName);
      if(hex){
        s.style.background = '#' + hex;
        s.style.color = pickTextColor(hex);
        s.style.border = '1px solid rgba(0,0,0,0.06)';
        if(isVeryLight(hex)) s.classList.add('light');
      }
      tags.appendChild(s);
    });
    div.appendChild(title); div.appendChild(meta); div.appendChild(desc); div.appendChild(tags);
    container.appendChild(div);
  });
}

function buildEntriesQuery(filters, offset=0, limit=ENTRIES_PAGE_SIZE){
  const params = new URLSearchParams();
  if(filters.from) params.set('from', filters.from);
  if(filters.to) params.set('to', filters.to);
  if(filters.submitter) params.set('submitter', filters.submitter);
  if(filters.tags) params.set('tags', filters.tags);
  if(filters.sort) params.set('sort', filters.sort);
  if(filters.order) params.set('order', filters.order);
  params.set('limit', String(limit));
  params.set('offset', String(offset));
  return params;
}

async function loadNextEntriesBatch(expectedVersion=entriesQueryVersion){
  if(expectedVersion !== entriesQueryVersion) return;
  if(loadingEntries || !hasMoreEntries) return;
  loadingEntries = true;
  const requestOffset = entriesOffset;
  try{
    const params = buildEntriesQuery(activeEntryFilters, requestOffset, ENTRIES_PAGE_SIZE);
    const data = await apiFetch('/api/entries.php?' + params.toString());
    if(expectedVersion !== entriesQueryVersion) return;
    renderEntries(data, requestOffset > 0);
    entriesOffset = requestOffset + data.length;
    hasMoreEntries = data.length === ENTRIES_PAGE_SIZE;
  } finally {
    if(expectedVersion === entriesQueryVersion) loadingEntries = false;
  }
}

async function loadAndShow(filters={}){
  entriesQueryVersion += 1;
  const requestVersion = entriesQueryVersion;
  loadingEntries = false;
  activeEntryFilters = Object.assign({}, filters);
  entriesOffset = 0;
  hasMoreEntries = true;
  await loadNextEntriesBatch(requestVersion);

  // If the first page doesn't fill the viewport, keep loading pages.
  while(requestVersion === entriesQueryVersion && hasMoreEntries && !loadingEntries && document.documentElement.scrollHeight <= (window.innerHeight + 120)){
    await loadNextEntriesBatch(requestVersion);
  }
}

function shouldLoadMoreEntries(){
  if(loadingEntries || !hasMoreEntries) return false;
  const scrollPosition = window.scrollY + window.innerHeight;
  const threshold = document.documentElement.scrollHeight - 300;
  return scrollPosition >= threshold;
}

document.addEventListener('DOMContentLoaded', async ()=>{
  const entryForm = document.getElementById('entryForm');
  const filterForm = document.getElementById('filterForm');
  const clearBtn = document.getElementById('clearFilters');

  await initDescriptionEditor();

  // load server tag list and attach suggestors to tag inputs
  await loadTagSuggestions();
  attachTagSuggestor(entryForm.querySelector('input[name="tags"]'));
  attachTagSuggestor(filterForm.querySelector('input[name="tags"]'));
  renderPopularTags();

  // load server submitter list and render popular submitters
  await loadSubmitterSuggestions();
  renderPopularSubmitters('popularSubmittersEntry', '#entryForm input[name="submitter"]');
  renderPopularSubmitters('popularSubmittersFilter', '#filterForm input[name="submitter"]');

  entryForm.addEventListener('submit', async e=>{
    e.preventDefault();
    const editor = getDescriptionEditor();
    if(editor) editor.save();
    const v = readForm(entryForm);
    const normalizedDescription = await normalizeImageSources(v.description);
    const description = sanitizeRichTextHtml(normalizedDescription);
    if(isRichTextBlank(description)){
      alert('Description is required.');
      focusDescriptionEditor();
      return;
    }

    const payload = {title:v.title, description, submitter:v.submitter, tags:(v.tags||'').split(',').map(s=>s.trim()).filter(Boolean)};
    try{
      await apiFetch('/api/entries.php', {method:'POST', body:JSON.stringify(payload)});
      entryForm.reset();
      clearDescriptionEditor();
      await loadTagSuggestions(); // refresh counts
      await loadSubmitterSuggestions(); // refresh counts
      renderPopularTags();
      renderPopularSubmitters('popularSubmittersEntry', '#entryForm input[name="submitter"]');
      renderPopularSubmitters('popularSubmittersFilter', '#filterForm input[name="submitter"]');
      loadAndShow(Object.fromEntries(new FormData(filterForm)));
    }catch(err){ alert('Error: '+err.message); }
  });

  filterForm.addEventListener('submit', e=>{
    e.preventDefault();
    const f = Object.fromEntries(new FormData(filterForm));
    if(f.from) f.from = new Date(f.from).toISOString();
    if(f.to) f.to = new Date(f.to).toISOString();
    loadAndShow(f);
  });

  clearBtn.addEventListener('click', ()=>{ filterForm.reset(); loadAndShow({}); });

  window.addEventListener('scroll', ()=>{
    if(shouldLoadMoreEntries()){
      loadNextEntriesBatch(entriesQueryVersion).catch(err=>{
        console.error('Failed to load additional entries', err);
      });
    }
  }, {passive:true});

  window.addEventListener('resize', ()=>{
    if(shouldLoadMoreEntries()){
      loadNextEntriesBatch(entriesQueryVersion).catch(err=>{
        console.error('Failed to load additional entries', err);
      });
    }
  });

  loadAndShow({});
});
