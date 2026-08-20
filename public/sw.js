const CACHE='workintel-shell-v15-2';
const SHELL=['/manifest.webmanifest','/favicon.ico'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).catch(()=>undefined));self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))));self.clients.claim();});
self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET')return;
  const url=new URL(request.url);
  if(url.origin!==self.location.origin||url.pathname.startsWith('/api/')||url.pathname.startsWith('/sanctum/'))return;
  if(request.mode==='navigate'){
    const privateShell=url.pathname==='/app'||url.pathname.startsWith('/app/')||url.pathname==='/seller'||url.pathname.startsWith('/seller/');
    if(privateShell){event.respondWith(fetch(request,{cache:'no-store'}));return;}
    event.respondWith(fetch(request));
    return;
  }
  if(/\.(?:js|css|woff2?|png|jpg|jpeg|svg|ico|webp)$/.test(url.pathname)){
    event.respondWith(caches.match(request).then(cached=>cached||fetch(request).then(response=>{const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(request,copy));return response;})));
  }
});
