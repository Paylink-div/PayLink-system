// =========================================================================
// sw.js - Service Worker for PayLink PWA
// =========================================================================

const CACHE_NAME = 'paylink-cache-v1';

// قائمة بالملفات الأساسية التي يجب تخزينها مؤقتاً عند التثبيت
const urlsToCache = [
  '/paylink_system/',
  '/paylink_system/login.php',
  '/paylink_system/css/style.css', // 💡 استبدلها بمسار ملف CSS الرئيسي لديك
  '/paylink_system/js/app.js',     // 💡 استبدلها بمسار ملف JS الرئيسي لديك
  '/paylink_system/db_connect.php', // لا يتم تخزينه في الكاش بل يتم استبعاده
  '/paylink_system/manifest.json',
  '/paylink_system/images/icons/icon-192x192.png'
];

// 1. مرحلة التثبيت (Install)
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        // تخزين الأصول الأساسية
        return cache.addAll(urlsToCache.filter(url => !url.endsWith('.php') && !url.includes('db_connect')));
      })
  );
  // تأمين العامل للانتقال مباشرة إلى مرحلة التفعيل دون انتظار التفعيل اليدوي
  self.skipWaiting(); 
});

// 2. مرحلة التفعيل (Activate)
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    // حذف أي نسخ قديمة من الكاش
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // المطالبة بالسيطرة على العملاء (Tabs) الموجودة فوراً
  return self.clients.claim();
});

// 3. مرحلة جلب البيانات (Fetch)
self.addEventListener('fetch', event => {
  // تجنب اعتراض الطلبات الخارجية وطلبات PHP التي تحتاج لاتصال قاعدة البيانات
  if (event.request.url.includes('google') || event.request.url.includes('.php')) {
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // إذا وجدنا الملف في الكاش، نعيده مباشرة
        if (response) {
          return response;
        }
        
        // إذا لم يجده في الكاش، نقوم بطلب الشبكة
        return fetch(event.request).then(
          response => {
            // تحقق إذا كان الاستجابة صالحة
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // تخزين الاستجابة الجديدة في الكاش
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });

            return response;
          }
        );
      })
    );
});