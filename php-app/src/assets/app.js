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

// Tag suggestions (populated from /api/tags.php)
let tagSuggestions = [];
async function loadTagSuggestions(){
  try{ const res = await fetch('/api/tags.php'); tagSuggestions = await res.json(); }catch(e){ tagSuggestions = []; }
}

// ADMIN: fetch and render tag management UI
async function fetchTags(){
  try{ return await apiFetch('/api/tags.php'); }catch(e){ return []; }
}

async function renderTagAdmin(){
  const listEl = document.getElementById('tagList');
  const tags = await fetchTags();
  if(!tags.length){ listEl.innerHTML = '<p>No tags defined</p>'; return; }
  const rows = tags.map(t=>`<tr data-tag="${escapeHtml(t.tag)}"><td><div class="swatch" style="background:#${escapeHtml(t.hex||'')};width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,.08)"></div></td><td class="tag-name">${escapeHtml(t.tag)}</td><td class="tag-hex">${escapeHtml(t.hex)}</td><td><button class="btn btn-small btn-edit">Edit</button> <button class="btn btn-small btn-danger btn-delete">Delete</button></td></tr>`).join('');
  listEl.innerHTML = `<table class="tag-table"><thead><tr><th></th><th>Tag</th><th>Hex</th><th></th></tr></thead><tbody>${rows}</tbody></table>`;

  // wire edit/delete
  Array.from(listEl.querySelectorAll('.btn-delete')).forEach(b=> b.addEventListener('click', async e=>{
    const row = e.target.closest('tr'); const tag = row.dataset.tag;
    if(!confirm(`Delete tag "${tag}"?`)) return;
    const res = await fetch('/api/tags.php?tag='+encodeURIComponent(tag), { method: 'DELETE' });
    if(!res.ok) { alert('Delete failed'); return; }
    await loadTagSuggestions(); renderTagAdmin();
  }));

  Array.from(listEl.querySelectorAll('.btn-edit')).forEach((b)=> b.addEventListener('click', e=>{
    const row = e.target.closest('tr');
    const tag = row.dataset.tag; const hex = row.querySelector('.tag-hex').textContent || '';
    row.innerHTML = `<td><div class="swatch" style="background:#${escapeHtml(hex)};width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,.08)"></div></td><td><input class="edit-tag" value="${escapeHtml(tag)}"></td><td><input class="edit-hex" value="${escapeHtml(hex)}"></td><td><button class="btn btn-small btn-save">Save</button> <button class="btn btn-small btn-cancel">Cancel</button></td>`;
    row.querySelector('.btn-cancel').addEventListener('click', ()=> renderTagAdmin());
    row.querySelector('.btn-save').addEventListener('click', async ()=>{
      const newTag = row.querySelector('.edit-tag').value.trim();
      const newHex = row.querySelector('.edit-hex').value.trim();
      try{
        const res = await fetch('/api/tags.php', { method: 'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ oldTag: tag, tag: newTag, hex: newHex }) });
        if(!res.ok) throw new Error(await res.text());
        await loadTagSuggestions(); renderTagAdmin();
      }catch(err){ alert('Update failed: '+err.message); }
    });
  }));
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
    const desc = document.createElement('div'); desc.textContent = e.description;
    const tags = document.createElement('div'); tags.className='tags';
    (e.tags||[]).forEach(t=>{ const s=document.createElement('span'); s.className='tag'; s.textContent=t; tags.appendChild(s); });
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

  entryForm.addEventListener('submit', async e=>{
    e.preventDefault();
    const v = readForm(entryForm);
    const payload = {title:v.title, description:v.description, submitter:v.submitter, tags:(v.tags||'').split(',').map(s=>s.trim()).filter(Boolean)};
    try{
      await apiFetch('/api/entries.php', {method:'POST', body:JSON.stringify(payload)});
      entryForm.reset();
      loadAndShow(Object.fromEntries(new FormData(filterForm)));
    }catch(err){ alert('Error: '+err.message); }
  });

  // tag admin form
  const tagAddForm = document.getElementById('tagAddForm');
  if(tagAddForm){
    tagAddForm.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(tagAddForm);
      const tag = (fd.get('tag')||'').toString().trim();
      const hex = ((fd.get('hex')||'').toString().trim()).replace(/^#/, '');
      if(!tag) return alert('tag required');
      try{
        await apiFetch('/api/tags.php', { method: 'POST', body: JSON.stringify({ tag, hex }) });
        tagAddForm.reset();
        await loadTagSuggestions();
        renderTagAdmin();
      }catch(err){ alert('Add tag failed: '+err.message); }
    });
  }

  document.getElementById('refreshTags')?.addEventListener('click', async ()=>{ await loadTagSuggestions(); renderTagAdmin(); });

  filterForm.addEventListener('submit', e=>{
    e.preventDefault();
    const f = Object.fromEntries(new FormData(filterForm));
    if(f.from) f.from = new Date(f.from).toISOString();
    if(f.to) f.to = new Date(f.to).toISOString();
    loadAndShow(f);
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
