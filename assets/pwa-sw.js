/* DARES PWA service worker: no caching of credentials, pages or API responses. */
const NAME='dares-piket-static-v2.1';
self.addEventListener('install', event => {event.waitUntil(self.skipWaiting());});
self.addEventListener('activate', event => {event.waitUntil((async()=>{const keys=await caches.keys();await Promise.all(keys.filter(k=>k.startsWith('dares-piket-')&&k!==NAME).map(k=>caches.delete(k)));await self.clients.claim();})());});
self.addEventListener('fetch', event => {
 const req=event.request;
 if(req.mode==='navigate' && new URL(req.url).searchParams.get('dares-piket-pwa')==='app'){
   event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>new Response('<!doctype html><html lang="nl"><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>DARES offline</title><body style="background:#101c2c;color:white;font:16px system-ui;padding:30px"><h1>DARES Piketrooster</h1><p>Je bent offline. Piketgegevens en bevestigingen zijn alleen beschikbaar met een internetverbinding.</p><button onclick="location.reload()">Opnieuw proberen</button></body></html>',{headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
   return;
 }
 // Do not intercept authenticated requests, POSTs, API endpoints, or downloadable calendars.
});
self.addEventListener('push',event=>{event.waitUntil((async()=>{let data={};try{data=event.data?event.data.json():{};}catch(e){}await self.registration.showNotification(data.title||'DARES Piketrooster',{body:data.body||'Nieuw piketbericht',icon:new URL('wp-content/plugins/dares-piketrooster/assets/icon-192.png',self.location.origin+'/').href,tag:data.tag||'dares-piket',data:{url:data.url||self.location.origin},renotify:false});})());});
self.addEventListener('notificationclick',event=>{event.notification.close();event.waitUntil((async()=>{const url=event.notification.data?.url||self.location.origin;const clientsList=await self.clients.matchAll({type:'window',includeUncontrolled:true});for(const c of clientsList){if(c.url===url){await c.focus();return;}}await self.clients.openWindow(url);})());});
