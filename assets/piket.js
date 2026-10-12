document.addEventListener('click',async function(e){const b=e.target.closest('[data-swap]');if(!b)return;const card=b.closest('.dares-piket-card');const sel=card.querySelector('select');const msg=card.querySelector('.dares-piket-message');if(!sel||!sel.value){msg.textContent='Geen medewerker geselecteerd.';return;}if(!confirm('Piketdienst overdragen aan '+sel.options[sel.selectedIndex].text+'?'))return;b.disabled=true;msg.textContent='Bezig met overdragen…';try{const body=new URLSearchParams({action:'dares_piket_swap',nonce:DaresPiket.nonce,group_id:b.dataset.swap,person_id:sel.value});const res=await fetch(DaresPiket.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},credentials:'same-origin',body});const json=await res.json();if(!json.success)throw new Error(typeof json.data==='string'?json.data:'Overdracht mislukt.');const fresh=document.createElement('div');fresh.innerHTML=json.data.html;const updated=fresh.querySelector('.dares-piket-card');if(updated){const panel=card.closest('.dares-piket-panel');const replacement=fresh.querySelector('.dares-piket-panel');if(panel&&replacement){for(const attr of panel.attributes){if(attr.name.startsWith('data-opt-'))replacement.setAttribute(attr.name,attr.value);}panel.replaceWith(replacement);}else card.replaceWith(updated);}else location.reload();}catch(err){msg.textContent=err.message;b.disabled=false;}});

// Automatisch verversen voor wijzigingen in het centrale rooster, zonder pagina-refresh.
(function(){
 async function refresh(panel){
  if(!panel.isConnected)return;
  const params=new URLSearchParams({action:'dares_piket_refresh'});
  ['groep','volgende','wisselen','contact','contact_openbaar','kop','logo'].forEach(key=>{
   const value=panel.dataset['opt'+key.split('_').map(part=>part.charAt(0).toUpperCase()+part.slice(1)).join('')];
   if(value!==undefined)params.set(key,value);
  });
  try{
   const response=await fetch(DaresPiket.ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params,cache:'no-store'});
   if(!response.ok)return;
   const result=await response.json();if(!result.success||!result.data?.html)return;
   const tmp=document.createElement('div');tmp.innerHTML=result.data.html;
   const next=tmp.querySelector('.dares-piket-panel');if(next&&panel.outerHTML!==next.outerHTML){if(panel.contains(document.activeElement)||panel.matches(':hover')){const oldCard=panel.querySelector('.dares-piket-card'),newCard=next.querySelector('.dares-piket-card');const oldName=oldCard?.querySelector('.dares-piket-name')?.textContent||'',newName=newCard?.querySelector('.dares-piket-name')?.textContent||'';const oldGroup=oldCard?.querySelector('.dares-piket-heading')?.textContent||'',newGroup=newCard?.querySelector('.dares-piket-heading')?.textContent||'';if(oldName===newName&&oldGroup===newGroup)return;}panel.replaceWith(next);}
  }catch(e){}
 }
 window.setInterval(()=>document.querySelectorAll('.dares-piket-panel[data-piket-refresh]').forEach(refresh),60000);
})();
