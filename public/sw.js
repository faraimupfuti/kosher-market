const CACHE='kosher-market-v2';
const SHELL=['/manifest.webmanifest'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET' || new URL(event.request.url).origin!==self.location.origin) return;
  const url=new URL(event.request.url);
  const staticAsset=/\.(?:css|js|png|jpg|jpeg|webp|gif|svg|woff2?)$/i.test(url.pathname) || url.pathname==='/manifest.webmanifest';
  if(staticAsset){
    event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request).then(response=>{const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,copy));return response;})));
    return;
  }
  event.respondWith(fetch(event.request).catch(()=>caches.match('/')));
});
