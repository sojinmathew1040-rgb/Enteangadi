import { useState, useEffect, useRef, useCallback } from 'react';
import { apiFetch, getBaseUrl } from './utils/apiClient';
import './App.css';

function base64ToBlob(base64, mimeType) {
  const bytes = atob(base64);
  const arr = new Uint8Array(bytes.length);
  for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
  return new Blob([arr], { type: mimeType });
}

function readSavedLocation() {
  try {
    const raw = localStorage.getItem('enteangadi_user_location');
    if (!raw) return null;
    const p = JSON.parse(raw);
    if (p && p.name && p.lat && p.lng) {
      return { name: p.name, lat: parseFloat(p.lat), lng: parseFloat(p.lng) };
    }
  } catch (_) {}
  return null;
}

const QUICK_REPLIES_BY_CAT = [
  { ids: [25, 27, 61], items: ['Is the insurance still active?', 'Are the registration documents (RC) clear?', 'How many kilometers has it run?', 'Can I come for a test drive?'] },
  { ids: [32], items: ['What is the security deposit amount?', 'Is there water and electricity backup?', 'Are bachelor tenants allowed?', 'When can I visit the property?'] },
  { ids: [39], items: ['What are the working hours?', 'Is this a full-time or part-time position?', 'Where is the office located?', 'What are the salary and benefits?'] },
  { ids: [11, 52], items: ['Is the warranty still valid?', 'Does it include the original bill and box?', 'Are there any scratches or functional defects?', 'What is the battery health percentage?'] },
  { ids: [80], items: ['Are the vaccinations up to date?', 'What is the age and breed?', 'Is the price negotiable?', 'Can you send more photos/videos?'] }
];

const DEFAULT_QUICK_REPLIES = [
  'Is this still available?',
  'I am interested. What is your final price?',
  'Where is your location to inspect this?'
];

const CATEGORY_SPEC_GROUPS = {
  cars: {
    match: [25, 26],
    groups: {
      'Popular Brands': ['Maruti Suzuki', 'Hyundai', 'Honda', 'Toyota', 'Tata', 'Mahindra'],
      'Body Types': ['Sedan', 'SUV', 'Hatchback'],
      'Fuel Types': ['Petrol', 'Diesel', 'Electric', 'CNG']
    }
  },
  mobiles: {
    match: [11, 12],
    groups: {
      Brands: ['Apple', 'Samsung', 'OnePlus', 'Xiaomi', 'Realme'],
      Storage: ['64 GB', '128 GB', '256 GB', '512 GB']
    }
  },
  properties: {
    match: [32, 33, 34, 35, 36, 37, 38],
    groups: {
      'Properties Type': ['House', 'Apartment', 'Villa', 'Plot'],
      'Size / Layout': ['1 BHK', '2 BHK', '3 BHK', '4 BHK']
    }
  }
};

const FALLBACK_CARD_IMG = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=400';

function App() {
  const backendUrl = getBaseUrl();
  const initialLoc = readSavedLocation();

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [navProgress, setNavProgress] = useState(0);
  const [error, setError] = useState(null);

  // Breathing logo + counting percentage splash states
  const [loaderVisible, setLoaderVisible] = useState(true);
  const [loaderHiding, setLoaderHiding] = useState(false);
  const [loaderPct, setLoaderPct] = useState(0);
  const [logoFailed, setLogoFailed] = useState(false);

  // Search & filter states
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [adTypeFilter, setAdTypeFilter] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [radius, setRadius] = useState('');
  const [sortBy, setSortBy] = useState('');
  const [spec, setSpec] = useState('');

  // Voice search states
  const [isSpeechSearching, setIsSpeechSearching] = useState(false);
  const [speechError, setSpeechError] = useState('');
  const [speechStatusText, setSpeechStatusText] = useState('');

  // Location states (initialized immediately from localStorage if available)
  const [locationStatus, setLocationStatus] = useState(
    initialLoc ? `📍 ${initialLoc.name.split(',')[0]} active` : 'Loading marketplace...'
  );
  const [locationActive, setLocationActive] = useState(initialLoc ? initialLoc.name : '');
  const [locationCoords, setLocationCoords] = useState(
    initialLoc ? { lat: initialLoc.lat, lng: initialLoc.lng } : null
  );

  // Modal & Chat states
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [chatOpen, setChatOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [myId, setMyId] = useState(null);
  const [selectedMessageAction, setSelectedMessageAction] = useState(null);

  // Voice recording & playback states
  const [isRecording, setIsRecording] = useState(false);
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  const [mediaRecorder, setMediaRecorder] = useState(null);
  const [recordingInterval, setRecordingInterval] = useState(null);
  const [audioStream, setAudioStream] = useState(null);
  const [activeAudio, setActiveAudio] = useState(null);

  // Lightbox states
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxImages, setLightboxImages] = useState([]);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  // Caches & refs for instant background preloading
  const queryCacheRef = useRef(new Map());
  const preloadedImagesRef = useRef(new Set());
  const backgroundPreloadedRef = useRef(false);
  const initialLoadDoneRef = useRef(false);
  const longPressTimers = useRef({});

  const preloadImage = useCallback((relPath) => {
    if (!relPath) return;
    const full = `${backendUrl}/${relPath}`;
    if (preloadedImagesRef.current.has(full)) return;
    preloadedImagesRef.current.add(full);
    const img = new Image();
    img.decoding = 'async';
    img.src = full;
  }, [backendUrl]);

  const buildQueryKey = useCallback((overrides = {}) => {
    const q = {
      search: overrides.search !== undefined ? overrides.search : searchTerm,
      category_id: overrides.category_id !== undefined ? overrides.category_id : categoryFilter,
      ad_type: overrides.ad_type !== undefined ? overrides.ad_type : adTypeFilter,
      min_price: overrides.min_price !== undefined ? overrides.min_price : minPrice,
      max_price: overrides.max_price !== undefined ? overrides.max_price : maxPrice,
      radius: overrides.radius !== undefined ? overrides.radius : radius,
      sort_by: overrides.sort_by !== undefined ? overrides.sort_by : sortBy,
      spec: overrides.spec !== undefined ? overrides.spec : spec
    };
    const params = new URLSearchParams();
    Object.entries(q).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) params.append(k, v);
    });
    return params.toString();
  }, [searchTerm, categoryFilter, adTypeFilter, minPrice, maxPrice, radius, sortBy, spec]);

  // Background preloader: warms popular filter tabs, categories & card images during idle time
  const startBackgroundPreload = useCallback((initialItems = []) => {
    initialItems.slice(0, 12).forEach((item) => preloadImage(item.main_image));
    if (backgroundPreloadedRef.current) return;
    backgroundPreloadedRef.current = true;

    const scheduleIdle = window.requestIdleCallback || ((cb) => setTimeout(cb, 250));
    scheduleIdle(async () => {
      // 1. Preload category catalog in background
      apiFetch('/api/catalog.php')
        .then((res) => {
          if (res && res.success && Array.isArray(res.categories)) {
            setCategories(res.categories);
          }
        })
        .catch(() => {});

      // 2. Preload popular filter combinations with concurrency = 2
      const presetQueries = [
        'ad_type=sell',
        'ad_type=rent',
        'ad_type=buy',
        'sort_by=price_asc',
        'sort_by=price_desc',
        'category_id=25',
        'category_id=11',
        'category_id=32'
      ];

      let idx = 0;
      const worker = async () => {
        while (idx < presetQueries.length) {
          const qs = presetQueries[idx++];
          if (queryCacheRef.current.has(qs)) continue;
          try {
            const res = await apiFetch(`/api/products.php?${qs}`, {
              headers: { 'X-Enteangadi-Preload': '1' }
            });
            if (res && res.success) {
              queryCacheRef.current.set(qs, { ts: Date.now(), data: res.products || [] });
              (res.products || []).slice(0, 6).forEach((p) => preloadImage(p.main_image));
            }
          } catch (_) {}
        }
      };
      await Promise.all([worker(), worker()]);
    });
  }, [preloadImage]);

  // Smooth counting percentage (0% -> 88%) while initial request is in flight
  useEffect(() => {
    if (!loaderVisible || initialLoadDoneRef.current) return;
    const timer = setInterval(() => {
      setLoaderPct((prev) => {
        if (prev >= 88) return prev;
        const step = prev < 45 ? 7 : prev < 72 ? 4 : 2;
        return Math.min(88, prev + step);
      });
    }, 45);
    return () => clearInterval(timer);
  }, [loaderVisible]);

  const finishInitialLoader = useCallback(() => {
    if (initialLoadDoneRef.current) return;
    initialLoadDoneRef.current = true;
    setLoaderPct(100);
    setTimeout(() => {
      setLoaderHiding(true);
      setTimeout(() => setLoaderVisible(false), 280);
    }, 140);
  }, []);

  // Fetch products with instant cache hit + background revalidation
  const fetchProducts = useCallback(async (overrides = undefined) => {
    const qs = buildQueryKey(overrides || {});
    const cached = queryCacheRef.current.get(qs);
    const now = Date.now();

    if (cached) {
      setProducts(cached.data);
      setLoading(false);
      setError(null);
      finishInitialLoader();
      // Fresh within 45s -> zero network wait needed
      if (now - cached.ts < 45000) return;
    } else {
      setLoading(true);
      setError(null);
      if (initialLoadDoneRef.current) setNavProgress(35);
    }

    try {
      const endpoint = qs ? `/api/products.php?${qs}` : '/api/products.php';
      if (!cached && initialLoadDoneRef.current) setNavProgress(70);
      const response = await apiFetch(endpoint);
      if (response.success) {
        const list = response.products || [];
        queryCacheRef.current.set(qs, { ts: Date.now(), data: list });
        setProducts(list);
        startBackgroundPreload(list);
      } else {
        throw new Error(response.message || 'Failed to fetch listings');
      }
    } catch (err) {
      if (!cached) {
        setError(err.message || 'Could not connect to the backend server.');
      }
    } finally {
      setLoading(false);
      finishInitialLoader();
      if (initialLoadDoneRef.current) {
        setNavProgress(100);
        setTimeout(() => setNavProgress(0), 180);
      }
    }
  }, [buildQueryKey, finishInitialLoader, startBackgroundPreload]);

  // Trigger fetch whenever reactive filters change (includes initial mount!)
  useEffect(() => {
    fetchProducts();
  }, [categoryFilter, adTypeFilter, sortBy, spec]); // eslint-disable-line react-hooks/exhaustive-deps

  // Non-blocking background location detection + mobile notifications setup
  useEffect(() => {
    if (!window.EnteangadiMobile) {
      window.EnteangadiMobile = {
        isRunningInMobile() {
          return !!(window.Capacitor && window.Capacitor.Plugins);
        },
        async requestNotificationPermission() {
          if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
              const res = await window.Capacitor.Plugins.LocalNotifications.requestPermissions();
              return res.display === 'granted';
            } catch (_) {
              return false;
            }
          }
          if (typeof Notification !== 'undefined') {
            try {
              return (await Notification.requestPermission()) === 'granted';
            } catch (_) {
              return false;
            }
          }
          return false;
        },
        showLocalNotification(senderName, messageText, senderId, productId) {
          const logoUrl = `${backendUrl}/uploads/logo/logo_enteangadi.png`;
          let bodyText = messageText || '';
          if (bodyText.startsWith('[AUDIO]:')) bodyText = '🎙️ Voice note';
          else if (bodyText.startsWith('[IMAGE]:')) bodyText = '📷 Shared photo';

          if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
              if (senderId && productId && localStorage.getItem(`muted_chat_${senderId}_${productId}`) === 'true') return;
              window.Capacitor.Plugins.LocalNotifications.schedule({
                notifications: [{
                  title: `Enteangadi - ${senderName}`,
                  body: bodyText,
                  id: Math.floor(Math.random() * 1000000),
                  schedule: { at: new Date(Date.now() + 100) },
                  sound: 'default',
                  smallIcon: 'res://ic_stat_logo',
                  largeIcon: 'res://ic_launcher',
                  actionTypeId: 'CHAT_MSG_ACTIONS',
                  extra: { sender_id: senderId, product_id: productId }
                }]
              });
            } catch (_) {}
          } else if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            new Notification(`Enteangadi - ${senderName}`, { body: bodyText, icon: logoUrl });
          }
        }
      };
    }

    const syncLocationWithBackend = (name, lat, lng) => {
      const fd = new FormData();
      fd.append('action', 'set_location');
      fd.append('location_name', name);
      fd.append('latitude', lat);
      fd.append('longitude', lng);
      fetch(`${backendUrl}/api/location.php`, { method: 'POST', body: fd, credentials: 'include' }).catch(() => {});
    };

    const detectLocationInBackground = async () => {
      if (initialLoc) {
        syncLocationWithBackend(initialLoc.name, initialLoc.lat, initialLoc.lng);
        return;
      }
      try {
        let lat = null;
        let lng = null;
        let resolvedCity = 'Kochi';

        if (window.Capacitor?.Plugins?.Geolocation) {
          try {
            const c = await window.Capacitor.Plugins.Geolocation.getCurrentPosition({ enableHighAccuracy: false, timeout: 3000 });
            lat = c.coords.latitude;
            lng = c.coords.longitude;
          } catch (_) {}
        }

        if (!lat && navigator.geolocation) {
          try {
            const pos = await new Promise((res, rej) =>
              navigator.geolocation.getCurrentPosition(res, rej, { enableHighAccuracy: false, timeout: 2800, maximumAge: 300000 })
            );
            lat = pos.coords.latitude;
            lng = pos.coords.longitude;
          } catch (_) {}
        }

        if (!lat) {
          try {
            const data = await Promise.any([
              fetch('https://ipwho.is/').then((r) => r.json()).then((d) => (d?.success ? { lat: +d.latitude, lng: +d.longitude, city: d.city } : Promise.reject())),
              fetch('https://ipapi.co/json/').then((r) => r.json()).then((d) => (d && !d.error ? { lat: +d.latitude, lng: +d.longitude, city: d.city } : Promise.reject()))
            ]);
            lat = data.lat;
            lng = data.lng;
            resolvedCity = data.city || 'Kochi';
          } catch (_) {}
        }

        if (lat && lng) {
          try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1`);
            const d = await res.json();
            const a = d.address || {};
            resolvedCity = a.village || a.town || a.suburb || a.municipality || a.city_district || a.city || a.state_district || resolvedCity;
          } catch (_) {}
        }

        const finalLat = lat || 9.94;
        const finalLng = lng || 76.27;
        setLocationStatus(`📍 ${resolvedCity.split(',')[0]} detected`);
        setLocationCoords({ lat: finalLat, lng: finalLng });
        setLocationActive(resolvedCity);
        try {
          localStorage.setItem('enteangadi_user_location', JSON.stringify({ name: resolvedCity, lat: finalLat, lng: finalLng }));
        } catch (_) {}
        syncLocationWithBackend(resolvedCity, finalLat, finalLng);
      } catch (_) {
        setLocationActive('Kochi');
        setLocationCoords({ lat: 9.94, lng: 76.27 });
      }
    };

    detectLocationInBackground();

    const timer = setTimeout(() => {
      window.EnteangadiMobile?.requestNotificationPermission?.();
      const ln = window.Capacitor?.Plugins?.LocalNotifications;
      if (ln) {
        try {
          ln.registerActionTypes({
            types: [{
              id: 'CHAT_MSG_ACTIONS',
              actions: [
                { id: 'reply', title: 'Reply', input: true, inputPlaceholder: 'Type a message...' },
                { id: 'read', title: 'Mark as read', foreground: false },
                { id: 'mute', title: 'Mute', foreground: false }
              ]
            }]
          });
          ln.addListener('localNotificationActionPerformed', (action) => {
            const extra = action.notification?.extra;
            if (!extra?.sender_id || !extra?.product_id) return;
            if (action.actionId === 'reply' && action.inputValue?.trim()) {
              const fd = new FormData();
              fd.append('action', 'send');
              fd.append('receiver_id', extra.sender_id);
              fd.append('product_id', extra.product_id);
              fd.append('message', action.inputValue.trim());
              fetch(`${backendUrl}/user/api_chat.php`, { method: 'POST', body: fd, credentials: 'include' })
                .then(() => fetchChatMessages());
            } else if (action.actionId === 'read') {
              fetch(`${backendUrl}/user/api_chat.php?action=fetch&other_id=${extra.sender_id}&product_id=${extra.product_id}`, { credentials: 'include' })
                .then(() => fetchChatMessages());
            } else if (action.actionId === 'mute') {
              localStorage.setItem(`muted_chat_${extra.sender_id}_${extra.product_id}`, 'true');
            }
          });
        } catch (_) {}
      }
    }, 2200);

    return () => clearTimeout(timer);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Session & Chat helpers
  const fetchSession = async () => {
    try {
      const res = await apiFetch('/api/session.php');
      if (res.success) {
        setMyId(res.user_id);
        return res.user_id;
      }
    } catch (_) {}
    return null;
  };

  const fetchChatMessages = async (currentUserId = myId) => {
    if (!selectedProduct) return;
    const effectiveMyId = currentUserId || myId;
    const buyerId = effectiveMyId === selectedProduct.user_id
      ? (selectedProduct.user_id == 1 ? 2 : 1)
      : effectiveMyId;

    try {
      const res = await apiFetch(`/user/api_chat.php?action=fetch&other_id=${selectedProduct.user_id}&product_id=${selectedProduct.id}&current_user_id=${buyerId}`);
      if (res.success) {
        const newMsgs = res.messages || [];
        setMessages((prev) => {
          if (prev.length > 0 && newMsgs.length > prev.length) {
            newMsgs.slice(prev.length).forEach((m) => {
              if (m.sender_id != effectiveMyId) {
                window.EnteangadiMobile?.showLocalNotification?.(m.sender_name || 'Seller/Buyer', m.message_text, m.sender_id, selectedProduct.id);
              }
            });
          }
          return newMsgs;
        });
      }
    } catch (_) {
    } finally {
      setLoadingMessages(false);
    }
  };

  const handleOpenChat = async () => {
    setChatOpen(true);
    setLoadingMessages(true);
    const uid = await fetchSession();
    fetchChatMessages(uid);
  };

  const postChatMessage = async (text) => {
    if (!text?.trim() || !selectedProduct) return;
    const buyerId = myId === selectedProduct.user_id ? (selectedProduct.user_id == 1 ? 2 : 1) : myId;
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver_id', selectedProduct.user_id);
    fd.append('product_id', selectedProduct.id);
    fd.append('message', text.trim());
    try {
      const r = await fetch(`${backendUrl}/user/api_chat.php`, { method: 'POST', body: fd, credentials: 'include' });
      const res = await r.json();
      if (res.success) fetchChatMessages(buyerId);
      else if (res.error) alert(res.error);
    } catch (_) {}
  };

  const sendTextMessage = () => {
    if (!newMessage.trim()) return;
    const txt = newMessage;
    setNewMessage('');
    postChatMessage(txt);
  };

  const deleteMessageReact = async (messageId, deleteType) => {
    const msg = deleteType === 'for_everyone'
      ? 'This message will be deleted for all participants. Are you sure?'
      : 'This message will be deleted for you. Are you sure?';
    if (!window.confirm(msg)) return;
    try {
      const res = await apiFetch(`/user/api_chat.php?action=delete_message&message_id=${messageId}&delete_type=${deleteType}`, { method: 'POST' });
      if (res.success) fetchChatMessages();
      else alert(res.error || 'Failed to delete message');
    } catch (_) {
      alert('Error deleting message');
    }
  };

  const startLongPress = (msgId, isMe) => {
    clearTimeout(longPressTimers.current[msgId]);
    longPressTimers.current[msgId] = setTimeout(() => {
      setSelectedMessageAction({ id: msgId, isMe });
      delete longPressTimers.current[msgId];
    }, 600);
  };

  const cancelLongPress = (msgId) => {
    if (longPressTimers.current[msgId]) {
      clearTimeout(longPressTimers.current[msgId]);
      delete longPressTimers.current[msgId];
    }
  };

  useEffect(() => {
    if (!chatOpen || !selectedProduct) return;
    const iv = setInterval(() => {
      if (!document.hidden) fetchChatMessages();
    }, 4500);
    return () => clearInterval(iv);
  }, [chatOpen, selectedProduct, myId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (chatOpen) {
      const box = document.getElementById('react-chat-box');
      if (box) box.scrollTop = box.scrollHeight;
    }
  }, [messages, chatOpen]);

  useEffect(() => {
    if (!lightboxOpen || !lightboxImages.length) return;
    const onKey = (e) => {
      if (e.key === 'ArrowLeft') setLightboxIndex((p) => (p - 1 + lightboxImages.length) % lightboxImages.length);
      else if (e.key === 'ArrowRight') setLightboxIndex((p) => (p + 1) % lightboxImages.length);
      else if (e.key === 'Escape') setLightboxOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [lightboxOpen, lightboxImages]);

  const groupMessages = (msgs) => {
    const grouped = [];
    for (const msg of msgs) {
      if (msg.message_text?.startsWith('[IMAGE]:')) {
        const url = msg.message_text.replace('[IMAGE]:', '');
        const isMe = msg.sender_id == myId;
        const last = grouped[grouped.length - 1];
        if (last?.type === 'image_group' && last.sender_id === msg.sender_id &&
            Math.abs(new Date(msg.created_at) - new Date(last.created_at)) < 60000) {
          last.images.push({ id: msg.id, url, msg });
          continue;
        }
        grouped.push({ type: 'image_group', id: `img_group_${msg.id}`, sender_id: msg.sender_id, isMe, created_at: msg.created_at, images: [{ id: msg.id, url, msg }] });
      } else {
        grouped.push({ type: 'normal', msg });
      }
    }
    return grouped;
  };

  const renderImageGroup = (group) => {
    const count = group.images.length;
    const openAt = (e, idx) => {
      e.stopPropagation();
      setLightboxImages(group.images.map((i) => `${backendUrl}/${i.url}`));
      setLightboxIndex(idx);
      setLightboxOpen(true);
    };

    if (count === 1) {
      return (
        <div className="chat-image-single" onClick={(e) => openAt(e, 0)}>
          <img src={`${backendUrl}/${group.images[0].url}`} className="message-chat-image" alt="Shared" loading="lazy" />
        </div>
      );
    }
    if (count === 2) {
      return (
        <div className="chat-image-grid grid-2">
          {group.images.map((img, idx) => (
            <div key={img.id} className="grid-item" onClick={(e) => openAt(e, idx)}>
              <img src={`${backendUrl}/${img.url}`} alt={`Photo ${idx + 1}`} loading="lazy" />
            </div>
          ))}
        </div>
      );
    }
    if (count === 3) {
      return (
        <div className="chat-image-grid grid-3">
          <div className="grid-left" onClick={(e) => openAt(e, 0)}>
            <img src={`${backendUrl}/${group.images[0].url}`} alt="Photo 1" loading="lazy" />
          </div>
          <div className="grid-right">
            {[1, 2].map((i) => (
              <div key={group.images[i].id} className="grid-sub-item" onClick={(e) => openAt(e, i)}>
                <img src={`${backendUrl}/${group.images[i].url}`} alt={`Photo ${i + 1}`} loading="lazy" />
              </div>
            ))}
          </div>
        </div>
      );
    }
    const remaining = count - 3;
    return (
      <div className="chat-image-grid grid-4">
        {group.images.slice(0, 4).map((img, idx) => (
          <div key={img.id} className="grid-item" onClick={(e) => openAt(e, idx)}>
            <img src={`${backendUrl}/${img.url}`} alt={`Photo ${idx + 1}`} loading="lazy" />
            {idx === 3 && remaining > 1 && <div className="grid-overlay"><span>+{remaining}</span></div>}
          </div>
        ))}
      </div>
    );
  };

  const getQuickReplies = (product) => {
    if (!product) return [];
    const catId = parseInt(product.category_id, 10);
    const parentId = parseInt(product.parent_category_id || 0, 10);
    const match = QUICK_REPLIES_BY_CAT.find((g) => g.ids.includes(catId) || g.ids.includes(parentId));
    return match ? match.items : DEFAULT_QUICK_REPLIES;
  };

  // Voice Note & Chat Image Upload
  const playVoiceNote = (url) => {
    if (activeAudio) activeAudio.pause();
    const audio = new Audio(url);
    setActiveAudio(audio);
    audio.play();
  };

  const uploadChatMedia = async (action, fieldName, blobOrFile) => {
    if (!selectedProduct) return;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('receiver_id', selectedProduct.user_id);
    fd.append('product_id', selectedProduct.id);
    fd.append(fieldName, blobOrFile);
    try {
      const r = await fetch(`${backendUrl}/user/api_chat.php`, { method: 'POST', body: fd, credentials: 'include' });
      const res = await r.json();
      if (res.success) fetchChatMessages();
      else if (res.error) alert(res.error);
    } catch (_) {}
  };

  const toggleRecording = async () => {
    if (isRecording) {
      stopRecording(true);
      return;
    }
    try {
      const micPlugin = window.Capacitor?.Plugins?.MicrophonePermission;
      if (micPlugin) {
        try { await micPlugin.checkPermission(); } catch (_) {}
        await micPlugin.startRecording();
        setIsRecording(true);
      } else {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('SECURE_CONTEXT_REQUIRED');
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        setAudioStream(stream);
        const recorder = new MediaRecorder(stream);
        const chunks = [];
        recorder.ondataavailable = (e) => { if (e.data?.size > 0) chunks.push(e.data); };
        recorder.onstop = async () => {
          if (chunks.length > 0) await uploadChatMedia('send_audio', 'audio_data', new Blob(chunks, { type: 'audio/wav' }));
          stream.getTracks().forEach((t) => t.stop());
        };
        recorder.start();
        setMediaRecorder(recorder);
        setIsRecording(true);
      }
      setRecordingSeconds(0);
      setRecordingInterval(setInterval(() => setRecordingSeconds((p) => p + 1), 1000));
    } catch (err) {
      alert(err.message === 'SECURE_CONTEXT_REQUIRED'
        ? 'Microphone access requires HTTPS or localhost.'
        : 'Microphone permission is required to record voice notes.');
    }
  };

  const stopRecording = (shouldSend) => {
    if (!isRecording) return;
    clearInterval(recordingInterval);
    setIsRecording(false);
    const micPlugin = window.Capacitor?.Plugins?.MicrophonePermission;
    if (micPlugin) {
      micPlugin.stopRecording().then(async (res) => {
        if (shouldSend && res?.base64 && recordingSeconds > 0) {
          await uploadChatMedia('send_audio', 'audio_data', base64ToBlob(res.base64, res.format || 'audio/mp4'));
        }
      }).catch(() => {});
    } else if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      if (!shouldSend) {
        mediaRecorder.onstop = () => audioStream?.getTracks().forEach((t) => t.stop());
      }
      mediaRecorder.stop();
    }
  };

  // Voice Search + Backup Recorder
  const startReactAudioRecorderFallback = async () => {
    const isMl = (localStorage.getItem('enteangadi_lang') || 'en') === 'ml';
    setSpeechStatusText(isMl ? 'ബാക്കപ്പ് എഞ്ചിൻ...' : 'Using backup engine...');
    setSpeechError(isMl ? 'സംസാരിക്കൂ (പരമാവധി 7 സെക്കൻഡ്)...' : 'Speak now (max 7s)...');
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
      const candidates = [
        ['audio/webm;codecs=opus', 'webm'],
        ['audio/webm', 'webm'],
        ['audio/ogg', 'ogg'],
        ['audio/mp4', 'mp4']
      ];
      const found = candidates.find(([mime]) => MediaRecorder.isTypeSupported(mime)) || ['audio/wav', 'wav'];
      const [recType, ext] = found;
      const recorder = new MediaRecorder(stream, { mimeType: recType });
      const chunks = [];
      recorder.ondataavailable = (ev) => { if (ev.data.size > 0) chunks.push(ev.data); };
      recorder.onstop = async () => {
        stream.getTracks().forEach((t) => t.stop());
        setSpeechStatusText(isMl ? 'പ്രൊസസ്സ് ചെയ്യുന്നു...' : 'Transcribing...');
        const fd = new FormData();
        fd.append('audio', new Blob(chunks, { type: recType }), `speech.${ext}`);
        try {
          const r = await fetch(`${backendUrl}/api/transcribe.php`, { method: 'POST', body: fd });
          const res = await r.json();
          if (res.success && res.transcript) {
            const term = res.transcript.trim();
            setSearchTerm(term);
            setIsSpeechSearching(false);
            setSpeechError('');
            setSpeechStatusText('');
            fetchProducts({ search: term });
          } else {
            setSpeechError(res.message || 'Error transcribing voice');
            setTimeout(() => { setIsSpeechSearching(false); setSpeechError(''); setSpeechStatusText(''); }, 4500);
          }
        } catch (_) {
          setIsSpeechSearching(false);
          setSpeechError('');
          setSpeechStatusText('');
        }
      };
      recorder.start();
      window.activeReactMediaRecorder = recorder;
      setTimeout(() => { if (recorder.state === 'recording') recorder.stop(); }, 7000);
    } catch (_) {
      setSpeechError(isMl ? 'മൈക്രോഫോൺ പെർമിഷൻ നൽകുക' : 'Microphone permissions denied.');
      setTimeout(() => { setIsSpeechSearching(false); setSpeechError(''); }, 2200);
    }
  };

  const startReactSpeechSearch = (e) => {
    e?.preventDefault();
    e?.stopPropagation();
    setIsSpeechSearching(false);
    setSpeechError('');
    setSpeechStatusText('');
    window.activeSpeechRecognition?.abort();
    if (window.activeReactMediaRecorder?.state === 'recording') {
      window.activeReactMediaRecorder.onstop = null;
      window.activeReactMediaRecorder.stop();
    }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
      alert('Speech recognition is not supported in this browser/device.');
      return;
    }
    try {
      const rec = new SR();
      rec.lang = navigator.language || 'en-US';
      rec.interimResults = false;
      rec.maxAlternatives = 1;
      setIsSpeechSearching(true);
      window.activeSpeechRecognition = rec;
      rec.onerror = () => startReactAudioRecorderFallback();
      rec.onend = () => setIsSpeechSearching(false);
      rec.onresult = (ev) => {
        const term = ev.results[0][0].transcript.trim();
        setSearchTerm(term);
        fetchProducts({ search: term });
      };
      rec.start();
    } catch (_) {
      setIsSpeechSearching(false);
    }
  };

  const resetFilters = () => {
    setSearchTerm('');
    setCategoryFilter('');
    setAdTypeFilter('');
    setMinPrice('');
    setMaxPrice('');
    setRadius('');
    setSortBy('');
    setSpec('');
    fetchProducts({ search: '', category_id: '', ad_type: '', min_price: '', max_price: '', radius: '', sort_by: '', spec: '' });
  };

  const formatPrice = (p) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(p || 0);
  const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : '');
  const formatTime = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
  const adBadgeLabel = (t) => (t === 'sell' ? 'FOR SALE' : t === 'rent' ? 'FOR RENT' : 'WANTED');

  const activeSpecGroup = Object.values(CATEGORY_SPEC_GROUPS).find((cfg) => cfg.match.includes(parseInt(categoryFilter, 10)));
  const isMl = (localStorage.getItem('enteangadi_lang') || 'en') === 'ml';

  return (
    <div className="app-container">
      {/* Breathing Logo + Counting Percentage Loader */}
      {loaderVisible && (
        <div className={`react-loader-wrapper${loaderHiding ? ' loader-hide' : ''}`}>
          <div className="react-loader-stage">
            <div className="react-loader-ring-1"></div>
            <div className="react-loader-ring-2"></div>
            <div className="react-loader-emblem">
              {!logoFailed ? (
                <img
                  src={`${backendUrl}/uploads/logo/logo_enteangadi.png`}
                  alt="Enteangadi"
                  onError={() => setLogoFailed(true)}
                />
              ) : (
                <span className="react-loader-fallback">🛍️</span>
              )}
            </div>
          </div>
          <div className="react-loader-percent">{loaderPct}%</div>
          <div className="react-loader-bar-track">
            <div className="react-loader-bar-fill" style={{ transform: `scaleX(${loaderPct / 100})` }}></div>
          </div>
          <div className="react-loader-tagline">Your Local Marketplace</div>
          <div className="react-loader-location-status">
            <span className="react-loader-spinner"></span>
            <span>{locationStatus}</span>
          </div>
        </div>
      )}

      {navProgress > 0 && (
        <div className="top-nav-progress">
          <div className="top-nav-progress-bar" style={{ transform: `scaleX(${navProgress / 100})` }}></div>
        </div>
      )}

      <header className="app-header">
        <div className="logo-section">
          {!logoFailed ? (
            <img src={`${backendUrl}/uploads/logo/logo_enteangadi.png`} alt="Enteangadi" className="header-logo-img" onError={() => setLogoFailed(true)} />
          ) : (
            <div className="app-icon">🛍️</div>
          )}
          <div>
            <h1 className="logo-title">Enteangadi</h1>
            <p className="logo-subtitle">Local Marketplace App</p>
          </div>
        </div>
        <div className="connection-badge">
          <span className="badge-dot"></span>
          Connected
        </div>
      </header>

      <div className="app-location-bar">
        <div className="location-display-react">
          <span className="loc-marker">📍</span>
          <span className="current-location-text-react">
            {locationActive ? (
              <>
                {locationActive.split(',')[0]}
                {locationCoords && (
                  <small className="coords-text-react">
                    ({locationCoords.lat.toFixed(2)}, {locationCoords.lng.toFixed(2)})
                  </small>
                )}
              </>
            ) : (
              <><span className="location-pulse-dot-react"></span> Locating...</>
            )}
          </span>
          <span className="chevron-icon">▼</span>
        </div>
      </div>

      <main className="app-main">
        <aside className="filters-sidebar">
          <h2 className="section-title">Search & Filters</h2>
          <form onSubmit={(e) => { e.preventDefault(); fetchProducts(); }} className="filters-form">
            <div className="form-group">
              <label>Search Listings</label>
              <div className="search-input-wrapper">
                <input
                  type="text"
                  placeholder="What are you looking for?"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="input-field"
                />
                <button
                  type="button"
                  onClick={startReactSpeechSearch}
                  className={`search-mic-btn${isSpeechSearching ? ' active' : ''}`}
                  title="Voice Search"
                >
                  {isSpeechSearching ? '🎙️' : '🎤'}
                </button>
                <button type="submit" className="search-btn">🔍</button>
              </div>
            </div>

            {categories.length > 0 && (
              <div className="form-group">
                <label>Category</label>
                <select
                  value={categoryFilter}
                  onChange={(e) => { setCategoryFilter(e.target.value); setSpec(''); }}
                  className="input-field"
                >
                  <option value="">All Categories</option>
                  {categories.filter((c) => !c.parent_id || c.parent_id == 0).map((cat) => (
                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                  ))}
                </select>
              </div>
            )}

            <div className="form-group">
              <label>Deal Type</label>
              <div className="segmented-control">
                {[
                  ['', 'All'],
                  ['sell', 'For Sale'],
                  ['rent', 'For Rent'],
                  ['buy', 'Wanted']
                ].map(([val, label]) => (
                  <button
                    key={val || 'all'}
                    type="button"
                    className={adTypeFilter === val ? 'active' : ''}
                    onClick={() => setAdTypeFilter(val)}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>

            <div className="form-group">
              <label>Price Range</label>
              <div className="price-inputs">
                <input type="number" placeholder="Min ₹" value={minPrice} onChange={(e) => setMinPrice(e.target.value)} className="input-field price-field" />
                <span>to</span>
                <input type="number" placeholder="Max ₹" value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} className="input-field price-field" />
              </div>
            </div>

            {locationCoords && (
              <div className="form-group">
                <label className="radius-label-row">
                  <span>Distance Radius</span>
                  <span className="radius-val">{radius ? `${radius} km` : 'All Kerala'}</span>
                </label>
                <input
                  type="range"
                  min="0"
                  max="100"
                  step="5"
                  value={radius || 0}
                  onChange={(e) => {
                    const v = parseInt(e.target.value, 10);
                    setRadius(v === 0 ? '' : v);
                  }}
                  className="radius-slider"
                />
                <small className="radius-hint">Centred on: {locationActive || 'Detected location'}</small>
              </div>
            )}

            <div className="form-group">
              <label>Sort By</label>
              <select value={sortBy} onChange={(e) => setSortBy(e.target.value)} className="input-field">
                <option value="">Newest Listings</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
                {locationCoords && <option value="distance">Nearest First</option>}
              </select>
            </div>

            {activeSpecGroup && (
              <div className="cat-filter-panel">
                <div className="cat-filter-head">
                  <span>Category Filters</span>
                  {spec && <button type="button" onClick={() => setSpec('')} className="cat-clear-link">Clear</button>}
                </div>
                {Object.entries(activeSpecGroup.groups).map(([title, opts]) => (
                  <div key={title}>
                    <span className="cat-group-label">{title}</span>
                    <div className="cat-chip-wrap">
                      {opts.map((opt) => (
                        <button
                          key={opt}
                          type="button"
                          onClick={() => setSpec(spec === opt ? '' : opt)}
                          className={`cat-chip${spec === opt ? ' selected' : ''}`}
                        >
                          {opt}
                        </button>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="filter-actions">
              <button type="button" onClick={() => fetchProducts()} className="apply-btn">Apply Filters</button>
              <button type="button" onClick={resetFilters} className="clear-btn">Reset</button>
            </div>
          </form>
        </aside>

        <section className="listings-section">
          {loading ? (
            <div className="status-container">
              <div className="spinner"></div>
              <p>Fetching local listings...</p>
            </div>
          ) : error ? (
            <div className="status-container error-state">
              <div className="error-icon">⚠️</div>
              <h3>Connection Issues</h3>
              <p>{error}</p>
              <button onClick={() => fetchProducts()} className="retry-btn">Retry Connection</button>
            </div>
          ) : products.length === 0 ? (
            <div className="status-container empty-state">
              <div className="empty-icon">📭</div>
              <h3>No Listings Found</h3>
              <p>Try broadening your criteria or reset the search filters.</p>
              <button onClick={resetFilters} className="retry-btn">Clear All Filters</button>
            </div>
          ) : (
            <div className="listings-grid">
              {products.map((product, idx) => (
                <div
                  key={product.id}
                  className="product-card"
                  onMouseEnter={() => preloadImage(product.main_image)}
                  onTouchStart={() => preloadImage(product.main_image)}
                  onClick={() => {
                    setSelectedProduct(product);
                    apiFetch(`/api/products.php?action=view&id=${product.id}`).catch(() => {});
                  }}
                >
                  <div className="card-image-wrapper">
                    {product.main_image ? (
                      <img
                        src={`${backendUrl}/${product.main_image}`}
                        alt={product.title}
                        className="card-image"
                        loading={idx < 6 ? 'eager' : 'lazy'}
                        decoding="async"
                        onError={(e) => { e.target.onerror = null; e.target.src = FALLBACK_CARD_IMG; }}
                      />
                    ) : (
                      <div className="placeholder-image">📦</div>
                    )}
                    <span className={`ad-type-badge ${product.type}`}>{adBadgeLabel(product.type)}</span>
                  </div>

                  <div className="card-info">
                    <span className="card-category">{product.category_name || 'Marketplace'}</span>
                    <h3 className="card-title">{product.title}</h3>
                    <div className="card-meta">
                      <span className="card-price">{formatPrice(product.price)}</span>
                      <span className="card-date">{formatDate(product.created_at)}</span>
                    </div>
                    {product.location_name && (
                      <div className="card-location">
                        <span>📍 {product.location_name.split(',')[0]}</span>
                        {product.distance !== undefined && product.distance !== null && (
                          <span className="card-distance">
                            🚴 {parseFloat(product.distance) < 1
                              ? `${Math.round(parseFloat(product.distance) * 1000)} m`
                              : `${parseFloat(product.distance).toFixed(1)} km`}
                          </span>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </main>

      <footer style={{ textAlign: 'center', padding: '1rem 1.5rem 2rem', fontSize: '0.84rem', color: 'var(--text)', borderTop: '1px solid var(--border)' }}>
        &copy; {new Date().getFullYear()} Enteangadi &bull;{' '}
        <a href={`${backendUrl}/privacy-policy`} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--primary-green)', fontWeight: 700, textDecoration: 'none' }}>
          Privacy Policy
        </a>
      </footer>

      {/* Message Actions Modal */}
      {selectedMessageAction && (
        <div className="modal-overlay high-z" onClick={() => setSelectedMessageAction(null)}>
          <div className="modal-content msg-action-card" onClick={(e) => e.stopPropagation()}>
            <h3>Message Options</h3>
            <div className="msg-action-stack">
              <button
                className="msg-action-btn danger-outline"
                onClick={() => { const id = selectedMessageAction.id; setSelectedMessageAction(null); deleteMessageReact(id, 'for_me'); }}
              >
                🗑️ Delete for me
              </button>
              {selectedMessageAction.isMe && (
                <button
                  className="msg-action-btn danger-solid"
                  onClick={() => { const id = selectedMessageAction.id; setSelectedMessageAction(null); deleteMessageReact(id, 'for_everyone'); }}
                >
                  🔄 Delete for everyone
                </button>
              )}
              <button className="msg-action-btn" onClick={() => setSelectedMessageAction(null)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* Product Detail & In-App Chat Modal */}
      {selectedProduct && (
        <div className="modal-overlay" onClick={() => { setSelectedProduct(null); setChatOpen(false); }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={() => { setSelectedProduct(null); setChatOpen(false); }}>×</button>
            <div className={`modal-body ${chatOpen ? 'chat-open' : ''}`}>
              <div className="modal-gallery">
                {selectedProduct.main_image ? (
                  <img
                    src={`${backendUrl}/${selectedProduct.main_image}`}
                    alt={selectedProduct.title}
                    className="modal-main-image"
                    onError={(e) => { e.target.onerror = null; e.target.src = FALLBACK_CARD_IMG; }}
                  />
                ) : (
                  <div className="modal-placeholder-image">📦 No Image</div>
                )}
              </div>

              {!chatOpen ? (
                <div className="modal-details">
                  <div className="modal-header-meta">
                    <span className={`ad-type-badge ${selectedProduct.type}`}>{adBadgeLabel(selectedProduct.type)}</span>
                    <span className="modal-id">Ref: {selectedProduct.unique_id || `ID-${selectedProduct.id}`}</span>
                  </div>

                  <h2 className="modal-title">{selectedProduct.title}</h2>
                  <div className="modal-price">{formatPrice(selectedProduct.price)}</div>

                  <div className="modal-seller-card">
                    <div className="seller-avatar">
                      {selectedProduct.seller_picture ? (
                        <img src={`${backendUrl}/${selectedProduct.seller_picture}`} alt="" />
                      ) : (
                        <span>{(selectedProduct.seller_username || 'S').charAt(0).toUpperCase()}</span>
                      )}
                    </div>
                    <div>
                      <div className="seller-meta-row">
                        <span>{selectedProduct.seller_username || 'Seller'}</span>
                        {selectedProduct.seller_verified && <span title="Verified Seller">✅</span>}
                        {selectedProduct.seller_rating > 0 && <span>⭐ {selectedProduct.seller_rating}</span>}
                      </div>
                      <small className="seller-sub">
                        {selectedProduct.seller_verified ? 'Verified Seller' : 'Registered Peer'} ({selectedProduct.seller_reviews_count || 0} reviews)
                      </small>
                    </div>
                  </div>

                  <hr className="divider" />
                  <h4 className="details-heading">Description</h4>
                  <p className="modal-desc">{selectedProduct.description || 'No description provided by seller.'}</p>

                  <h4 className="details-heading">Listing Metrics</h4>
                  <div className="metrics-grid">
                    <div className="metric-box">
                      <span className="metric-label">Views</span>
                      <span className="metric-val">👁️ {selectedProduct.views || 0}</span>
                    </div>
                    <div className="metric-box">
                      <span className="metric-label">Listed on</span>
                      <span className="metric-val">📅 {formatDate(selectedProduct.created_at)}</span>
                    </div>
                  </div>

                  {selectedProduct.location_name && (
                    <>
                      <h4 className="details-heading">Location</h4>
                      <p className="modal-location">📍 {selectedProduct.location_name}</p>
                      {selectedProduct.latitude && selectedProduct.longitude && (
                        <a
                          href={`https://www.google.com/maps/search/?api=1&query=${selectedProduct.latitude},${selectedProduct.longitude}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="map-link-btn"
                        >
                          🗺️ View on Google Maps
                        </a>
                      )}
                    </>
                  )}

                  <hr className="divider" />
                  <div className="contact-methods">
                    <button className="contact-btn chat-btn" onClick={handleOpenChat}>💬 Chat with Seller</button>
                    {selectedProduct.whatsapp_number && (
                      <a
                        href={`https://wa.me/${selectedProduct.whatsapp_number.replace(/\D/g, '')}?text=Hi,%20I'm%20interested%20in%20your%20listing:%20${encodeURIComponent(selectedProduct.title)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="contact-btn whatsapp-btn"
                      >
                        💬 Chat on WhatsApp
                      </a>
                    )}
                    <button
                      className="contact-btn share-btn"
                      onClick={() => {
                        const shareUrl = `${window.location.origin}/product.php?id=${selectedProduct.id}`;
                        const text = `*Enteangadi Local listing* 🛍️🌟\n\n*Item:* ${selectedProduct.title}\n*Price:* ₹${parseFloat(selectedProduct.price).toLocaleString('en-IN')}\n*Location:* ${selectedProduct.location_name || 'Kerala'}\n\nNalla smart deal aanu! 🥳 Nokki thiranj vaangan click cheyoo:\n${shareUrl}`;
                        window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
                      }}
                    >
                      🟢 Share on WhatsApp
                    </button>
                  </div>
                </div>
              ) : (
                <div className="chat-pane-app">
                  <div className="chat-pane-header">
                    <button className="chat-back-btn" onClick={() => setChatOpen(false)}>←</button>
                    <div className="chat-user-info">
                      <span className="chat-username">Chat with Seller</span>
                      <span className="chat-status">Online</span>
                    </div>
                  </div>

                  <div className="chat-messages-container" id="react-chat-box">
                    {loadingMessages ? (
                      <div className="chat-loading"><div className="chat-mini-spinner"></div> Loading messages...</div>
                    ) : messages.length === 0 ? (
                      <div className="chat-empty">Start the conversation about {selectedProduct.title}</div>
                    ) : (
                      groupMessages(messages).map((group) => {
                        const isImgGroup = group.type === 'image_group';
                        const refMsg = isImgGroup ? group.images[group.images.length - 1].msg : group.msg;
                        const isMe = isImgGroup ? group.isMe : refMsg.sender_id == myId;
                        const time = new Date(refMsg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const pressProps = {
                          onMouseDown: () => startLongPress(refMsg.id, isMe),
                          onMouseUp: () => cancelLongPress(refMsg.id),
                          onMouseMove: () => cancelLongPress(refMsg.id),
                          onTouchStart: () => startLongPress(refMsg.id, isMe),
                          onTouchEnd: () => cancelLongPress(refMsg.id),
                          onTouchMove: () => cancelLongPress(refMsg.id)
                        };

                        return (
                          <div key={isImgGroup ? group.id : refMsg.id} className={`chat-bubble-row ${isMe ? 'msg-me' : 'msg-other'}`}>
                            <div className={`chat-bubble${isImgGroup ? ' chat-bubble-images' : ''}`} {...pressProps}>
                              {isImgGroup ? (
                                renderImageGroup(group)
                              ) : refMsg.message_text.startsWith('[AUDIO]:') ? (
                                <div className="message-audio-player">
                                  <button
                                    type="button"
                                    className="react-audio-btn"
                                    onClick={(e) => { e.stopPropagation(); playVoiceNote(`${backendUrl}/${refMsg.message_text.replace('[AUDIO]:', '')}`); }}
                                  >
                                    ▶️
                                  </button>
                                  <span className="audio-duration-tag">Voice note</span>
                                </div>
                              ) : (
                                <div className="message-text">{refMsg.message_text}</div>
                              )}
                              <div className="msg-meta-row">
                                <span>{time}</span>
                                {isMe && (
                                  <span className={`msg-status${refMsg.is_read == 1 ? ' seen' : ''}`}>
                                    {refMsg.is_read == 1 || refMsg.is_delivered == 1 ? '✓✓' : '✓'}
                                  </span>
                                )}
                              </div>
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>

                  {selectedProduct.status === 'deleted' ? (
                    <div className="chat-disabled-banner">⚠️ This product has been deleted by the seller.</div>
                  ) : (
                    <>
                      <div className="react-quick-replies">
                        {getQuickReplies(selectedProduct).map((reply, idx) => (
                          <button key={idx} className="quick-reply-chip" onClick={() => postChatMessage(reply)}>
                            {reply}
                          </button>
                        ))}
                      </div>
                      <div className="chat-input-row">
                        {isRecording ? (
                          <div className="recording-status">
                            <span className="recording-dot"></span>
                            <span className="recording-timer">{formatTime(recordingSeconds)}</span>
                            <button type="button" className="btn-discard" onClick={() => stopRecording(false)}>🗑️</button>
                          </div>
                        ) : (
                          <>
                            <input
                              type="text"
                              placeholder="Type a message..."
                              value={newMessage}
                              onChange={(e) => setNewMessage(e.target.value)}
                              onKeyDown={(e) => e.key === 'Enter' && sendTextMessage()}
                              className="chat-text-input"
                            />
                            <button
                              type="button"
                              className="btn-image-react"
                              onClick={() => document.getElementById('react-chat-image-input').click()}
                              title="Send Image"
                            >
                              📷
                            </button>
                            <input
                              type="file"
                              id="react-chat-image-input"
                              accept="image/*"
                              multiple
                              hidden
                              onChange={async (e) => {
                                if (e.target.files?.length) {
                                  for (const f of Array.from(e.target.files)) {
                                    await uploadChatMedia('send_image', 'image_data', f);
                                  }
                                  e.target.value = '';
                                }
                              }}
                            />
                          </>
                        )}
                        <button type="button" className={`btn-mic-react ${isRecording ? 'recording-active' : ''}`} onClick={toggleRecording}>
                          {isRecording ? '⏹️' : '🎙️'}
                        </button>
                        {!isRecording && <button className="btn-send-react" onClick={sendTextMessage}>✈️</button>}
                      </div>
                    </>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Voice Search Overlay */}
      {isSpeechSearching && (
        <div className="modal-overlay high-z">
          <div className="voice-modal-card">
            <div className="voice-pulse-wrap">
              <div className="voice-ring"></div>
              <div className="voice-ring delay"></div>
              <div className="voice-mic-core">🎤</div>
            </div>
            <h3>
              {speechStatusText || (speechError
                ? (isMl ? 'തടസ്സം നേരിട്ടു' : 'Failed to record')
                : (isMl ? 'കേൾക്കുന്നു...' : 'Listening...'))}
            </h3>
            <p style={{ color: speechError ? '#ef4444' : '#64748b', fontSize: '13px', margin: '8px 0 20px' }}>
              {speechError || (isMl ? 'സംസാരിക്കൂ...' : 'Speak now...')}
            </p>
            <button
              type="button"
              className="voice-btn-done"
              onClick={() => {
                if (window.activeReactMediaRecorder?.state === 'recording') window.activeReactMediaRecorder.stop();
                else window.activeSpeechRecognition?.stop();
              }}
            >
              Done
            </button>
            <button
              type="button"
              className="voice-btn-cancel"
              onClick={() => {
                setIsSpeechSearching(false);
                setSpeechError('');
                setSpeechStatusText('');
                window.activeSpeechRecognition?.abort();
                if (window.activeReactMediaRecorder?.state === 'recording') {
                  window.activeReactMediaRecorder.onstop = null;
                  window.activeReactMediaRecorder.stop();
                }
              }}
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* Fullscreen Chat Image Lightbox */}
      {lightboxOpen && (
        <div className="lightbox-overlay" onClick={() => setLightboxOpen(false)}>
          <button className="lightbox-close" onClick={() => setLightboxOpen(false)}>×</button>
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            {lightboxImages.length > 1 && (
              <button className="lightbox-nav-btn prev" onClick={() => setLightboxIndex((p) => (p - 1 + lightboxImages.length) % lightboxImages.length)}>‹</button>
            )}
            <div className="lightbox-image-container">
              <img src={lightboxImages[lightboxIndex]} alt={`View ${lightboxIndex + 1}`} className="lightbox-main-image" />
            </div>
            {lightboxImages.length > 1 && (
              <button className="lightbox-nav-btn next" onClick={() => setLightboxIndex((p) => (p + 1) % lightboxImages.length)}>›</button>
            )}
            <div className="lightbox-counter">{lightboxIndex + 1} / {lightboxImages.length}</div>
          </div>
        </div>
      )}
    </div>
  );
}

export default App;
