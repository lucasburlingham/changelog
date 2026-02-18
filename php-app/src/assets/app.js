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

function renderEntries(list){
  const container = document.getElementById('entries');
  container.innerHTML = '';
  if(!list.length) { container.innerHTML = '<p>No entries</p>'; return; }
  list.forEach(e=>{
    const div = document.createElement('div'); div.className='entry';
    const title = document.createElement('div'); title.innerHTML = `<strong>${escapeHtml(e.title)}</strong>`;
    const meta = document.createElement('div'); meta.className='meta';
    const d = new Date(e.timestamp);
    meta.textContent = `${d.toLocaleString()} — ${e.submitter||'—'}`;
    const desc = document.createElement('div'); desc.className = 'description'; desc.textContent = e.description;
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

async function loadAndShow(filters={}){
  const params = new URLSearchParams();
  if(filters.from) params.set('from', filters.from);
  if(filters.to) params.set('to', filters.to);
  if(filters.submitter) params.set('submitter', filters.submitter);
  if(filters.tags) params.set('tags', filters.tags);
  if(filters.sort) params.set('sort', filters.sort);
  if(filters.order) params.set('order', filters.order);
  const data = await apiFetch('/api/entries.php?'+params.toString());
  renderEntries(data);
}

document.addEventListener('DOMContentLoaded', async ()=>{
  const entryForm = document.getElementById('entryForm');
  const filterForm = document.getElementById('filterForm');
  const clearBtn = document.getElementById('clearFilters');

  // load server tag list and attach suggestors to tag inputs
  await loadTagSuggestions();
  attachTagSuggestor(entryForm.querySelector('input[name="tags"]'));
  attachTagSuggestor(filterForm.querySelector('input[name="tags"]'));
  renderPopularTags();

  entryForm.addEventListener('submit', async e=>{
    e.preventDefault();
    const v = readForm(entryForm);
    const payload = {title:v.title, description:v.description, submitter:v.submitter, tags:(v.tags||'').split(',').map(s=>s.trim()).filter(Boolean)};
    try{
      await apiFetch('/api/entries.php', {method:'POST', body:JSON.stringify(payload)});
      entryForm.reset();
      await loadTagSuggestions(); // refresh counts
      renderPopularTags();
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

  loadAndShow({});
});
