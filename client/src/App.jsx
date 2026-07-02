import { useState, useEffect, useRef } from 'react';
import { apiFetch, getBaseUrl } from './utils/apiClient';
import './App.css';

function base64ToBlob(base64, mimeType) {
  const byteCharacters = atob(base64);
  const byteNumbers = new Array(byteCharacters.length);
  for (let i = 0; i < byteCharacters.length; i++) {
    byteNumbers[i] = byteCharacters.charCodeAt(i);
  }
  const byteArray = new Uint8Array(byteNumbers);
  return new Blob([byteArray], { type: mimeType });
}

function App() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Search and filter states
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [adTypeFilter, setAdTypeFilter] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [radius, setRadius] = useState('');
  const [sortBy, setSortBy] = useState('');
  const [spec, setSpec] = useState('');
  const [isSpeechSearching, setIsSpeechSearching] = useState(false);
  const [speechError, setSpeechError] = useState('');
  const [speechStatusText, setSpeechStatusText] = useState('');
  const [categories, setCategories] = useState([]);

  // Selected product for detailed modal view
  const [selectedProduct, setSelectedProduct] = useState(null);

  // Quick test for backend connection URL
  const backendUrl = getBaseUrl();

  // Chat States
  const [chatOpen, setChatOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [myId, setMyId] = useState(null);
  const [myUsername, setMyUsername] = useState('');
  const [selectedMessageAction, setSelectedMessageAction] = useState(null); // { id: number, isMe: boolean } or null

  const longPressTimers = useRef({});

  const startLongPress = (msgId, isMe) => {
    if (longPressTimers.current[msgId]) {
      clearTimeout(longPressTimers.current[msgId]);
    }
    longPressTimers.current[msgId] = setTimeout(() => {
      setSelectedMessageAction({ id: msgId, isMe });
      delete longPressTimers.current[msgId];
    }, 600); // 600ms hold duration for long press
  };

  const cancelLongPress = (msgId) => {
    if (longPressTimers.current[msgId]) {
      clearTimeout(longPressTimers.current[msgId]);
      delete longPressTimers.current[msgId];
    }
  };

  // Location Auto-Detection States for Splash Screen
  const [locationStatus, setLocationStatus] = useState('Detecting location...');
  const [locationActive, setLocationActive] = useState('');
  const [locationCoords, setLocationCoords] = useState(null);
  const [loaderVisible, setLoaderVisible] = useState(true);
  const [loaderClass, setLoaderClass] = useState('react-loader-wrapper');

  // Voice Recording States
  const [isRecording, setIsRecording] = useState(false);
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  const [mediaRecorder, setMediaRecorder] = useState(null);
  const [recordingInterval, setRecordingInterval] = useState(null);
  const [audioStream, setAudioStream] = useState(null);

  // Audio playback state
  const [activeAudio, setActiveAudio] = useState(null);

  // Lightbox States for chat image gallery
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxImages, setLightboxImages] = useState([]);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  // Fetch session info
  const fetchSession = async () => {
    try {
      const response = await apiFetch('/api/session.php');
      if (response.success) {
        setMyId(response.user_id);
        setMyUsername(response.username);
        return response.user_id;
      }
    } catch (e) {
      console.error("Session fetch failed", e);
    }
    return null;
  };

  // Open Chat
  const handleOpenChat = async () => {
    setChatOpen(true);
    setLoadingMessages(true);
    const userId = await fetchSession();
    fetchChatMessages(userId);
  };

  // Fetch Messages
  // Request notification permissions and initialize fallback window.EnteangadiMobile on mount
  // Request notification permissions, initialize fallback window.EnteangadiMobile, and auto-detect location on mount
  useEffect(() => {
    if (!window.EnteangadiMobile) {
      window.EnteangadiMobile = {
        isRunningInMobile: function () {
          return !!(window.Capacitor && window.Capacitor.Plugins);
        },
        requestNotificationPermission: async function () {
          if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
              const result = await window.Capacitor.Plugins.LocalNotifications.requestPermissions();
              console.log("Capacitor notification permissions result inside React app:", result);
              return result.display === 'granted';
            } catch (e) {
              console.warn("Capacitor notification permission request error inside React app:", e);
              return false;
            }
          } else if (typeof Notification !== 'undefined') {
            try {
              const permission = await Notification.requestPermission();
              console.log("Web standard notification permission inside React app:", permission);
              return permission === 'granted';
            } catch (e) {
              console.warn("Web standard notification permission request error inside React app:", e);
              return false;
            }
          }
          return false;
        },
        showLocalNotification: function (senderName, messageText, senderId, productId) {
          let logoUrl = `${backendUrl}/uploads/logo/logo_1778137117.jpg`;
          let bodyText = messageText || '';
          if (bodyText.startsWith('[AUDIO]:')) {
            bodyText = '🎙️ Voice note';
          } else if (bodyText.startsWith('[IMAGE]:')) {
            bodyText = '📷 Shared photo';
          }

          if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
              if (senderId && productId) {
                const isMuted = localStorage.getItem(`muted_chat_${senderId}_${productId}`) === 'true';
                if (isMuted) return;
              }

              window.Capacitor.Plugins.LocalNotifications.schedule({
                notifications: [{
                  title: "Enteangadi - " + senderName,
                  body: bodyText,
                  id: Math.floor(Math.random() * 1000000),
                  schedule: { at: new Date(Date.now() + 100) },
                  sound: "default",
                  smallIcon: "res://ic_stat_logo",
                  largeIcon: "res://ic_launcher",
                  actionTypeId: 'CHAT_MSG_ACTIONS',
                  extra: {
                    sender_id: senderId,
                    product_id: productId
                  }
                }]
              });
              console.log("Capacitor local notification scheduled successfully inside React app.");
            } catch (err) {
              console.error("Failed to schedule Capacitor native local notification inside React app:", err);
            }
          } else if (typeof Notification !== 'undefined') {
            if (Notification.permission === 'granted') {
              new Notification("Enteangadi - " + senderName, {
                body: bodyText,
                icon: logoUrl
              });
            } else if (Notification.permission === 'default') {
              Notification.requestPermission().then(perm => {
                if (perm === 'granted') {
                  new Notification("Enteangadi - " + senderName, {
                    body: bodyText,
                    icon: logoUrl
                  });
                }
              });
            }
          }
        }
      };
    }

    // Auto-detect location inside standalone React App context on startup
    const autoDetectReactLocation = async () => {
      try {
        setLocationStatus('Detecting location...');

        // Restore location from localStorage if saved previously
        let savedLoc = null;
        try {
          savedLoc = localStorage.getItem('enteangadi_user_location');
        } catch (e) {
          console.warn('localStorage is not accessible:', e);
        }

        if (savedLoc) {
          try {
            const parsed = JSON.parse(savedLoc);
            if (parsed && parsed.name && parsed.lat && parsed.lng) {
              setLocationStatus(`📍 ${parsed.name.split(',')[0]} active!`);
              setLocationCoords({ lat: parseFloat(parsed.lat), lng: parseFloat(parsed.lng) });
              setLocationActive(parsed.name);
              fetchProducts();

              // Sync with server session silently in the background
              const formData = new FormData();
              formData.append('action', 'set_location');
              formData.append('location_name', parsed.name);
              formData.append('latitude', parsed.lat);
              formData.append('longitude', parsed.lng);
              fetch(`${backendUrl}/api/location.php`, {
                method: 'POST',
                body: formData,
                credentials: 'include'
              }).catch(err => console.warn("Background session sync failed:", err));

              // Fade out and hide splash loader
              setTimeout(() => {
                setLoaderClass('react-loader-wrapper loader-hide');
                setTimeout(() => {
                  setLoaderVisible(false);
                }, 800);
              }, 1200);

              return;
            }
          } catch (e) {
            console.warn("Failed parsing saved location:", e);
          }
        }

        let lat = null;
        let lng = null;

        // 1. Capacitor Native Geolocation
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Geolocation) {
          try {
            const coordinates = await window.Capacitor.Plugins.Geolocation.getCurrentPosition({
              enableHighAccuracy: true,
              timeout: 4000
            });
            lat = coordinates.coords.latitude;
            lng = coordinates.coords.longitude;
          } catch (e) {
            console.warn("React native Geolocation failed, falling back to browser:", e);
          }
        }

        // 2. Standard Browser Geolocation
        if (!lat && navigator.geolocation) {
          try {
            const pos = await new Promise((resolve, reject) => {
              navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 3500
              });
            });
            lat = pos.coords.latitude;
            lng = pos.coords.longitude;
          } catch (e) {
            console.warn("React web Geolocation failed, falling back to IP:", e);
          }
        }

        // 3. Resilient IP fallback
        let resolvedCity = 'Kochi';
        if (!lat) {
          // A. Try ipapi.co
          try {
            const res = await fetch('https://ipapi.co/json/');
            const data = await res.json();
            if (data && !data.error) {
              lat = data.latitude;
              lng = data.longitude;
              resolvedCity = data.city || 'Kochi';
            }
          } catch (e1) {
            console.warn("React IP Geolocation (ipapi.co) failed, trying ipwhois:", e1);
          }

          // B. Try ipwho.is if still no coordinates found
          if (!lat) {
            try {
              const res = await fetch('https://ipwho.is/');
              const data = await res.json();
              if (data && data.success) {
                lat = parseFloat(data.latitude);
                lng = parseFloat(data.longitude);
                resolvedCity = data.city || 'Kochi';
              }
            } catch (e2) {
              console.warn("React IP Geolocation (ipwhois) failed, trying ip-api:", e2);
            }
          }

          // C. Try ip-api.com if still no coordinates found
          if (!lat) {
            try {
              const res = await fetch('http://ip-api.com/json/');
              const data = await res.json();
              if (data && data.status === 'success') {
                lat = data.lat;
                lng = data.lon;
                resolvedCity = data.city || 'Kochi';
              }
            } catch (e3) {
              console.error("React IP Geolocation (ip-api) failed:", e3);
            }
          }
        }

        // 4. Reverse geocode via OpenStreetMap server if coordinates found
        if (lat && lng) {
          try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
            const data = await res.json();
            resolvedCity = data.address.village ||
              data.address.hamlet ||
              data.address.local_authority ||
              data.address.municipality ||
              data.address.village_panchayat ||
              data.address.town ||
              data.address.suburb ||
              data.address.neighbourhood ||
              data.address.city_district ||
              data.address.city ||
              data.address.state_district ||
              resolvedCity ||
              'Kochi';
          } catch (e) {
            console.warn("React reverse geocode failed:", e);
          }
        }

        setLocationStatus(`📍 ${resolvedCity.split(',')[0]} detected!`);
        setLocationCoords({ lat: lat || 9.94, lng: lng || 76.27 });
        setLocationActive(resolvedCity);
        fetchProducts();

        // Cache coordinates and name in localStorage
        try {
          localStorage.setItem('enteangadi_user_location', JSON.stringify({
            name: resolvedCity,
            lat: lat || 9.94,
            lng: lng || 76.27
          }));
        } catch (e) {
          console.warn("Failed to write to localStorage:", e);
        }

        // Synchronize with PHP session variables asynchronously
        const formData = new FormData();
        formData.append('action', 'set_location');
        formData.append('location_name', resolvedCity);
        formData.append('latitude', lat || 9.94);
        formData.append('longitude', lng || 76.27);

        try {
          await fetch(`${backendUrl}/api/location.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
          });
        } catch (postErr) {
          console.warn("React location session sync failed:", postErr);
        }

        // 1.2s delay for premium branding view feedback
        setTimeout(() => {
          setLoaderClass('react-loader-wrapper loader-hide');
          setTimeout(() => {
            setLoaderVisible(false);
          }, 800);
        }, 1200);

      } catch (err) {
        console.error("Auto-location failed in React:", err);
        setLocationStatus('Selection fallback active');
        setLocationActive('Kochi');
        setLocationCoords({ lat: 9.94, lng: 76.27 });
        fetchProducts();

        setTimeout(() => {
          setLoaderClass('react-loader-wrapper loader-hide');
          setTimeout(() => {
            setLoaderVisible(false);
          }, 800);
        }, 1200);
      }
    };

    autoDetectReactLocation();

    // Trigger permission requests 2.5 seconds after mounting to prevent splash stutters
    const timer = setTimeout(() => {
      if (window.EnteangadiMobile && typeof window.EnteangadiMobile.requestNotificationPermission === 'function') {
        window.EnteangadiMobile.requestNotificationPermission();
      }

      // Set up LocalNotification Action types and action listeners
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications) {
        try {
          window.Capacitor.Plugins.LocalNotifications.registerActionTypes({
            types: [
              {
                id: 'CHAT_MSG_ACTIONS',
                actions: [
                  {
                    id: 'reply',
                    title: 'Reply',
                    input: true,
                    inputPlaceholder: 'Type a message...'
                  },
                  {
                    id: 'read',
                    title: 'Mark as read',
                    foreground: false
                  },
                  {
                    id: 'mute',
                    title: 'Mute',
                    foreground: false
                  }
                ]
              }
            ]
          });

          // Listen for actions
          window.Capacitor.Plugins.LocalNotifications.addListener('localNotificationActionPerformed', (action) => {
            console.log("React app localNotificationActionPerformed:", action);
            const extra = action.notification.extra;
            if (!extra || !extra.sender_id || !extra.product_id) return;

            const senderId = extra.sender_id;
            const productId = extra.product_id;

            if (action.actionId === 'reply' && action.inputValue) {
              const text = action.inputValue.trim();
              if (text) {
                const formData = new FormData();
                formData.append('action', 'send');
                formData.append('receiver_id', senderId);
                formData.append('product_id', productId);
                formData.append('message', text);

                fetch(`${backendUrl}/user/api_chat.php`, {
                  method: 'POST',
                  body: formData,
                  credentials: 'include'
                })
                .then(res => res.json())
                .then(resData => {
                  console.log("React interactive reply response:", resData);
                  fetchChatMessages();
                });
              }
            } else if (action.actionId === 'read') {
              fetch(`${backendUrl}/user/api_chat.php?action=fetch&other_id=${senderId}&product_id=${productId}`, {
                credentials: 'include'
              })
              .then(res => res.json())
              .then(resData => {
                console.log("React interactive mark-read response:", resData);
                fetchChatMessages();
              });
            } else if (action.actionId === 'mute') {
              localStorage.setItem(`muted_chat_${senderId}_${productId}`, 'true');
              console.log("React chat muted:", senderId, productId);
            }
          });
        } catch (e) {
          console.warn("React LocalNotifications configuration failed:", e);
        }
      }
    }, 2500);

    return () => clearTimeout(timer);
  }, []);

  // Fetch Messages
  const fetchChatMessages = async (currentUserId = myId) => {
    if (!selectedProduct) return;

    // Resolve dynamic sender (to avoid chatting with yourself during testing)
    const effectiveMyId = currentUserId || myId;
    const buyerId = (effectiveMyId === selectedProduct.user_id)
      ? (selectedProduct.user_id == 1 ? 2 : 1)
      : effectiveMyId;

    try {
      const response = await apiFetch(`/user/api_chat.php?action=fetch&other_id=${selectedProduct.user_id}&product_id=${selectedProduct.id}&current_user_id=${buyerId}`);
      if (response.success) {
        const newMsgs = response.messages || [];

        // Use functional state updates to compare safely against current messages and trigger notifications
        setMessages(prevMessages => {
          if (prevMessages.length > 0 && newMsgs.length > prevMessages.length) {
            const incoming = newMsgs.slice(prevMessages.length);
            incoming.forEach(msg => {
              const isMe = msg.sender_id == effectiveMyId;
              if (!isMe && window.EnteangadiMobile && typeof window.EnteangadiMobile.showLocalNotification === 'function') {
                window.EnteangadiMobile.showLocalNotification(msg.sender_name || 'Seller/Buyer', msg.message_text);
              }
            });
          }
          return newMsgs;
        });
      }
    } catch (err) {
      console.error("Fetch messages failed", err);
    } finally {
      setLoadingMessages(false);
    }
  };

  // Delete message in React app
  const deleteMessageReact = async (messageId, deleteType) => {
    const isEveryone = deleteType === 'for_everyone';
    const confirmMsg = isEveryone 
      ? "This message will be deleted for all participants. Are you sure?" 
      : "This message will be deleted for you. Are you sure?";
    
    if (!window.confirm(confirmMsg)) return;

    try {
      const response = await apiFetch(`/user/api_chat.php?action=delete_message&message_id=${messageId}&delete_type=${deleteType}`, {
        method: 'POST'
      });
      if (response.success) {
        fetchChatMessages();
      } else {
        alert(response.error || "Failed to delete message");
      }
    } catch (err) {
      console.error("Delete message failed", err);
      alert("Error deleting message");
    }
  };

  // Poll chat messages
  useEffect(() => {
    let interval;
    if (chatOpen && selectedProduct) {
      interval = setInterval(() => {
        if (!document.hidden) {
          fetchChatMessages();
        }
      }, 4000);
    }
    return () => clearInterval(interval);
  }, [chatOpen, selectedProduct, myId]);

  // Auto scroll chat to bottom
  useEffect(() => {
    if (chatOpen) {
      const chatBox = document.getElementById('react-chat-box');
      if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
      }
    }
  }, [messages, chatOpen]);

  // Keyboard navigation for Lightbox
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (!lightboxOpen || lightboxImages.length === 0) return;
      if (e.key === 'ArrowLeft') {
        setLightboxIndex((prev) => (prev - 1 + lightboxImages.length) % lightboxImages.length);
      } else if (e.key === 'ArrowRight') {
        setLightboxIndex((prev) => (prev + 1) % lightboxImages.length);
      } else if (e.key === 'Escape') {
        setLightboxOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [lightboxOpen, lightboxImages]);

  // Group consecutive images from the same sender sent within 60 seconds of each other
  const groupMessages = (msgs) => {
    const grouped = [];
    for (let i = 0; i < msgs.length; i++) {
      const msg = msgs[i];
      const isImage = msg.message_text && msg.message_text.startsWith('[IMAGE]:');

      if (isImage) {
        const imageUrl = msg.message_text.replace('[IMAGE]:', '');
        const isMe = msg.sender_id == myId;
        const lastGroup = grouped[grouped.length - 1];

        if (
          lastGroup &&
          lastGroup.type === 'image_group' &&
          lastGroup.sender_id === msg.sender_id
        ) {
          const firstMsgTime = new Date(lastGroup.created_at).getTime();
          const currentMsgTime = new Date(msg.created_at).getTime();

          if (Math.abs(currentMsgTime - firstMsgTime) < 60000) {
            lastGroup.images.push({
              id: msg.id,
              url: imageUrl,
              msg: msg
            });
            continue;
          }
        }

        grouped.push({
          type: 'image_group',
          id: `img_group_${msg.id}`,
          sender_id: msg.sender_id,
          isMe: isMe,
          created_at: msg.created_at,
          images: [{
            id: msg.id,
            url: imageUrl,
            msg: msg
          }]
        });
      } else {
        grouped.push({
          type: 'normal',
          msg: msg
        });
      }
    }
    return grouped;
  };

  // WhatsApp-style Image Collage/Grid Renderer
  const renderImageGroup = (group) => {
    const count = group.images.length;

    const handleImageClick = (e, clickedIndex) => {
      e.stopPropagation();
      setLightboxImages(group.images.map(img => `${backendUrl}/${img.url}`));
      setLightboxIndex(clickedIndex);
      setLightboxOpen(true);
    };

    if (count === 1) {
      const img = group.images[0];
      return (
        <div className="chat-image-single" onClick={(e) => handleImageClick(e, 0)}>
          <img
            src={`${backendUrl}/${img.url}`}
            className="message-chat-image"
            alt="Shared Photo"
          />
        </div>
      );
    }

    if (count === 2) {
      return (
        <div className="chat-image-grid grid-2">
          {group.images.map((img, idx) => (
            <div key={img.id} className="grid-item" onClick={(e) => handleImageClick(e, idx)}>
              <img src={`${backendUrl}/${img.url}`} alt={`Shared Photo ${idx + 1}`} />
            </div>
          ))}
        </div>
      );
    }

    if (count === 3) {
      return (
        <div className="chat-image-grid grid-3">
          <div className="grid-left" onClick={(e) => handleImageClick(e, 0)}>
            <img src={`${backendUrl}/${group.images[0].url}`} alt="Shared Photo 1" />
          </div>
          <div className="grid-right">
            <div className="grid-sub-item" onClick={(e) => handleImageClick(e, 1)}>
              <img src={`${backendUrl}/${group.images[1].url}`} alt="Shared Photo 2" />
            </div>
            <div className="grid-sub-item" onClick={(e) => handleImageClick(e, 2)}>
              <img src={`${backendUrl}/${group.images[2].url}`} alt="Shared Photo 3" />
            </div>
          </div>
        </div>
      );
    }

    // 4 or more photos
    const displayImages = group.images.slice(0, 4);
    const remaining = count - 3; // WhatsApp standard count shows +2 if there are 5 images (count=5 => 5-3 = 2)

    return (
      <div className="chat-image-grid grid-4">
        {displayImages.map((img, idx) => {
          const isLast = idx === 3;
          return (
            <div
              key={img.id}
              className="grid-item"
              onClick={(e) => handleImageClick(e, idx)}
            >
              <img src={`${backendUrl}/${img.url}`} alt={`Shared Photo ${idx + 1}`} />
              {isLast && remaining > 1 && (
                <div className="grid-overlay">
                  <span>+{remaining}</span>
                </div>
              )}
            </div>
          );
        })}
      </div>
    );
  };

  // Send Text Message
  const sendTextMessage = async () => {
    if (!newMessage.trim() || !selectedProduct) return;
    const text = newMessage.trim();
    setNewMessage('');

    const effectiveMyId = myId;
    const buyerId = (effectiveMyId === selectedProduct.user_id)
      ? (selectedProduct.user_id == 1 ? 2 : 1)
      : effectiveMyId;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', selectedProduct.user_id);
    formData.append('product_id', selectedProduct.id);
    formData.append('message', text);

    try {
      const response = await fetch(`${backendUrl}/user/api_chat.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });
      const result = await response.json();
      if (result.success) {
        fetchChatMessages(buyerId);
      }
    } catch (err) {
      console.error("Failed to send message:", err);
    }
  };

  const getQuickReplies = (product) => {
    if (!product) return [];
    const catId = parseInt(product.category_id);
    const parentId = parseInt(product.parent_category_id || 0);

    const defaultReplies = [
      "Is this still available?",
      "I am interested. What is your final price?",
      "Where is your location to inspect this?"
    ];

    if (catId === 25 || parentId === 25 || catId === 27 || parentId === 27 || catId === 61 || parentId === 61) {
      return [
        "Is the insurance still active?",
        "Are the registration documents (RC) clear?",
        "How many kilometers has it run?",
        "Can I come for a test drive?"
      ];
    } else if (catId === 32 || parentId === 32) {
      return [
        "What is the security deposit amount?",
        "Is there water and electricity backup?",
        "Are bachelor tenants allowed?",
        "When can I visit the property?"
      ];
    } else if (catId === 39 || parentId === 39) {
      return [
        "What are the working hours?",
        "Is this a full-time or part-time position?",
        "Where is the office located?",
        "What are the salary and benefits?"
      ];
    } else if (catId === 11 || parentId === 11 || catId === 52 || parentId === 52) {
      return [
        "Is the warranty still valid?",
        "Does it include the original bill and box?",
        "Are there any scratches or functional defects?",
        "What is the battery health percentage?"
      ];
    } else if (catId === 80 || parentId === 80) {
      return [
        "Are the vaccinations up to date?",
        "What is the age and breed?",
        "Is the price negotiable?",
        "Can you send more photos/videos?"
      ];
    }

    return defaultReplies;
  };

  const sendQuickReplyMessage = async (text) => {
    if (!text || !selectedProduct) return;

    const effectiveMyId = myId;
    const buyerId = (effectiveMyId === selectedProduct.user_id)
      ? (selectedProduct.user_id == 1 ? 2 : 1)
      : effectiveMyId;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', selectedProduct.user_id);
    formData.append('product_id', selectedProduct.id);
    formData.append('message', text);

    try {
      const response = await fetch(`${backendUrl}/user/api_chat.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });
      const result = await response.json();
      if (result.success) {
        fetchChatMessages(buyerId);
      } else {
        alert(result.error || "Failed to send quick reply");
      }
    } catch (err) {
      console.error("Failed to send quick reply:", err);
    }
  };

  // Play audio voice note
  const playVoiceNote = (url) => {
    if (activeAudio) {
      activeAudio.pause();
    }
    const audio = new Audio(url);
    setActiveAudio(audio);
    audio.play();
  };

  // Toggle Voice Recording
  const toggleRecording = async () => {
    if (!isRecording) {
      try {
        const isMobile = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.MicrophonePermission;
        if (isMobile) {
          try {
            await window.Capacitor.Plugins.MicrophonePermission.checkPermission();
          } catch (e) {
            console.warn("Capacitor custom microphone permission request error:", e);
          }
          await window.Capacitor.Plugins.MicrophonePermission.startRecording();
          setIsRecording(true);
        } else {
          if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("SECURE_CONTEXT_REQUIRED");
          }
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          setAudioStream(stream);

          const recorder = new MediaRecorder(stream);
          const chunks = [];

          recorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) {
              chunks.push(e.data);
            }
          };

          recorder.onstop = async () => {
            const audioBlob = new Blob(chunks, { type: 'audio/wav' });
            if (chunks.length > 0) {
              await uploadVoiceNote(audioBlob);
            }
            stream.getTracks().forEach(t => t.stop());
          };

          recorder.start();
          setMediaRecorder(recorder);
          setIsRecording(true);
        }

        setRecordingSeconds(0);
        const interval = setInterval(() => {
          setRecordingSeconds(prev => prev + 1);
        }, 1000);
        setRecordingInterval(interval);

      } catch (err) {
        console.error("Microphone capture failed", err);
        if (err.message === "SECURE_CONTEXT_REQUIRED" || err.name === "TypeError") {
          alert("Microphone access requires a secure context (HTTPS or localhost).\n\nTo test voice recording on your phone, run 'adb reverse tcp:80 tcp:80' and configure your Capacitor config to load from 'http://localhost/Enteangadi'.");
        } else {
          alert("Microphone permission is required to record voice notes.");
        }
      }
    } else {
      stopRecording(true);
    }
  };

  // Stop Recording
  const stopRecording = (shouldSend) => {
    if (!isRecording) return;

    clearInterval(recordingInterval);
    setIsRecording(false);

    const isMobile = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.MicrophonePermission;
    if (isMobile) {
      window.Capacitor.Plugins.MicrophonePermission.stopRecording().then(async (result) => {
        if (shouldSend && result && result.base64 && recordingSeconds > 0) {
          const audioBlob = base64ToBlob(result.base64, result.format || 'audio/mp4');
          await uploadVoiceNote(audioBlob);
        }
      }).catch(err => {
        console.error("Native voice recording stop failed:", err);
      });
    } else {
      if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        if (!shouldSend) {
          mediaRecorder.onstop = () => {
            if (audioStream) {
              audioStream.getTracks().forEach(t => t.stop());
            }
          };
        }
        mediaRecorder.stop();
      }
    }
  };

  const discardRecording = () => {
    stopRecording(false);
  };

  // Upload Audio note
  const uploadVoiceNote = async (audioBlob) => {
    if (!selectedProduct) return;

    const formData = new FormData();
    formData.append('action', 'send_audio');
    formData.append('receiver_id', selectedProduct.user_id);
    formData.append('product_id', selectedProduct.id);
    formData.append('audio_data', audioBlob);

    try {
      const response = await fetch(`${backendUrl}/user/api_chat.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });
      const result = await response.json();
      if (result.success) {
        fetchChatMessages();
      }
    } catch (err) {
      console.error("Audio note upload failed:", err);
    }
  };

  // Upload Chat Image
  const uploadChatImage = async (imageFile) => {
    if (!selectedProduct) return;

    const formData = new FormData();
    formData.append('action', 'send_image');
    formData.append('receiver_id', selectedProduct.user_id);
    formData.append('product_id', selectedProduct.id);
    formData.append('image_data', imageFile);

    try {
      const response = await fetch(`${backendUrl}/user/api_chat.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include'
      });
      const result = await response.json();
      if (result.success) {
        fetchChatMessages();
      } else {
        alert(result.error || "Failed to send image.");
      }
    } catch (err) {
      console.error("Image upload failed:", err);
    }
  };

  // Format timer helper
  const formatTime = (secs) => {
    const m = String(Math.floor(secs / 60)).padStart(2, '0');
    const s = String(secs % 60).padStart(2, '0');
    return `${m}:${s}`;
  };

  // Load products & categories from API
  const fetchProducts = async () => {
    setLoading(true);
    setError(null);
    try {
      // Build query string
      const params = new URLSearchParams();
      if (searchTerm) params.append('search', searchTerm);
      if (categoryFilter) params.append('category_id', categoryFilter);
      if (adTypeFilter) params.append('ad_type', adTypeFilter);
      if (minPrice) params.append('min_price', minPrice);
      if (maxPrice) params.append('max_price', maxPrice);
      if (radius) params.append('radius', radius);
      if (sortBy) params.append('sort_by', sortBy);
      if (spec) params.append('spec', spec);

      const queryString = params.toString() ? `?${params.toString()}` : '';
      const response = await apiFetch(`/api/products.php${queryString}`);

      if (response.success) {
        setProducts(response.products || []);
      } else {
        throw new Error(response.message || 'Failed to fetch listings');
      }
    } catch (err) {
      console.error(err);
      setError(err.message || 'Could not connect to the backend server. Make sure your local PHP server (Apache) is running.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, [categoryFilter, adTypeFilter, sortBy, spec]);

  // Clean formatted price helper
  const formatPrice = (price) => {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0
    }).format(price);
  };

  // Human readable dates helper
  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
  };

  const startReactSpeechSearch = (e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    // Clean up active speech sessions and reset mic capture channels
    setIsSpeechSearching(false);
    setSpeechError('');
    setSpeechStatusText('');
    if (window.activeSpeechRecognition) {
      window.activeSpeechRecognition.abort();
    }
    if (window.activeReactMediaRecorder && window.activeReactMediaRecorder.state === 'recording') {
      window.activeReactMediaRecorder.onstop = null;
      window.activeReactMediaRecorder.stop();
    }
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      alert("Speech recognition is not supported in this browser/device.");
      return;
    }

    let recognition;
    try {
      recognition = new SpeechRecognition();
    } catch (err) {
      console.error("Failed to initialize Speech Recognition:", err);
      alert("Failed to initialize Speech Recognition. Please verify browser microphone permissions or security settings.");
      return;
    }

    recognition.lang = navigator.language || 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    setIsSpeechSearching(true);
    window.activeSpeechRecognition = recognition;

    recognition.onerror = async (e) => {
      console.error("Speech recognition error:", e);
      startReactAudioRecorderFallback();
    };

    recognition.onend = () => {
      setIsSpeechSearching(false);
    };

    recognition.onresult = (event) => {
      const transcript = event.results[0][0].transcript.trim(); // Trim spaces!
      setSearchTerm(transcript);
      setTimeout(() => {
        fetchProducts();
      }, 100);
    };

    try {
      recognition.start();
    } catch (err) {
      console.error("Failed to start speech recognition:", err);
      setIsSpeechSearching(false);
      alert("Microphone is currently unavailable or in use by another application.");
    }
  };

  const startReactAudioRecorderFallback = async () => {
    const isMalayalam = (localStorage.getItem('enteangadi_lang') || 'en') === 'ml';
    setSpeechStatusText(isMalayalam ? 'ബാക്കപ്പ് എഞ്ചിൻ...' : 'Using backup engine...');
    setSpeechError(isMalayalam ? 'സംസാരിക്കൂ (പരമാവധി 7 സെക്കൻഡ്)...' : 'Speak now (max 7s)...');

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        }
      });

      let recType = 'audio/webm;codecs=opus';
      let ext = 'webm';
      if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        recType = 'audio/webm;codecs=opus';
        ext = 'webm';
      } else if (MediaRecorder.isTypeSupported('audio/webm')) {
        recType = 'audio/webm';
        ext = 'webm';
      } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
        recType = 'audio/ogg';
        ext = 'ogg';
      } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
        recType = 'audio/mp4';
        ext = 'mp4';
      } else {
        recType = 'audio/wav';
        ext = 'wav';
      }

      const recorder = new MediaRecorder(stream, { mimeType: recType });
      const chunks = [];

      recorder.ondataavailable = (event) => {
        if (event.data.size > 0) chunks.push(event.data);
      };

      recorder.onstop = async () => {

        const audioBlob = new Blob(chunks, { type: recType });
        stream.getTracks().forEach(t => t.stop());

        setSpeechStatusText(isMalayalam ? 'പ്രൊസസ്സ് ചെയ്യുന്നു...' : 'Transcribing...');
        setSpeechError(isMalayalam ? 'ദയവായി കാത്തിരിക്കൂ...' : 'Processing audio...');

        const formData = new FormData();
        formData.append('audio', audioBlob, `speech.${ext}`);

        try {
          const response = await fetch(`${backendUrl}/api/transcribe.php`, {
            method: 'POST',
            body: formData
          });
          const result = await response.json();

          if (result.success && result.transcript) {
            setSearchTerm(result.transcript.trim());
            setIsSpeechSearching(false);
            setSpeechError('');
            setSpeechStatusText('');
            setTimeout(() => {
              fetchProducts();
            }, 100);
          } else {
            if (result.error === 'API_KEY_NOT_CONFIGURED') {
              const isBrave = (navigator.brave && typeof navigator.brave.isBrave === 'function')
                ? await navigator.brave.isBrave()
                : false;
              if (isBrave) {
                setSpeechError(isMalayalam
                  ? 'Brave-ൽ വോയ്‌സ് വർക്ക് ചെയ്യാൻ Settings -> Privacy-ൽ "Use Google Services for Web Speech API" ഓൺ ചെയ്യുക'
                  : 'Brave blocks voice. Enable "Use Google Services for Web Speech API" in Brave Settings -> Privacy.');
              } else {
                setSpeechError(isMalayalam
                  ? 'കോൺഫിഗറേഷനിൽ ഗൂഗിൾ/ഗ്രോക്ക് എപിഐ കീ നൽകുക'
                  : 'Speech API key is not configured in config.php');
              }
            } else {
              setSpeechError(result.message || 'Error transcribing voice');
            }
            setTimeout(() => {
              setIsSpeechSearching(false);
              setSpeechError('');
              setSpeechStatusText('');
            }, 7000);
          }
        } catch (err) {
          console.error("Transcription failed", err);
          setIsSpeechSearching(false);
          setSpeechError('');
          setSpeechStatusText('');
        }
      };

      recorder.start();
      window.activeReactMediaRecorder = recorder;

      setTimeout(() => {
        if (recorder.state === 'recording') {
          recorder.stop();
        }
      }, 7000);

    } catch (err) {
      console.error("Mic access failed", err);
      setSpeechError(isMalayalam ? 'മൈക്രോഫോൺ പെർമിഷൻ നൽകുക' : 'Microphone permissions denied.');
      setTimeout(() => {
        setIsSpeechSearching(false);
        setSpeechError('');
      }, 2500);
    }
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    fetchProducts();
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
    fetchProducts();
  };

  return (
    <div className="app-container">
      {loaderVisible && (
        <div className={loaderClass}>
          <div className="react-loader-logo">🛍️ Enteangadi</div>
          <div className="react-loader-tagline">Your Local Marketplace</div>
          <div className="react-loader-location-status">
            <span className="react-loader-spinner"></span>
            <span>{locationStatus}</span>
          </div>
        </div>
      )}

      {/* Header Navigation */}
      <header className="app-header">
        <div className="logo-section">
          <div className="app-icon">🛍️</div>
          <div>
            <h1 className="logo-title">Enteangadi</h1>
            <p className="logo-subtitle">Local Marketplace App</p>
          </div>
        </div>
        <div className="connection-badge">
          <span className="badge-dot"></span>
          Connected to {backendUrl}
        </div>
      </header>

      {/* Location Bar */}
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
              <>
                <span className="location-pulse-dot-react"></span> Locating...
              </>
            )}
          </span>
          <span className="chevron-icon">▼</span>
        </div>
      </div>

      {/* Main Content Layout */}
      <main className="app-main">
        {/* Search & Filters Column */}
        <aside className="filters-sidebar">
          <h2 className="section-title">Search & Filters</h2>
          <form onSubmit={handleSearchSubmit} className="filters-form">
            <div className="form-group">
              <label>Search Listings</label>
              <div className="search-input-wrapper" style={{ display: 'flex', alignItems: 'center', position: 'relative' }}>
                <input
                  type="text"
                  placeholder="What are you looking for?"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="input-field"
                  style={{ paddingRight: '40px' }}
                />
                <button 
                  type="button" 
                  onClick={startReactSpeechSearch}
                  className="search-mic-btn"
                  style={{
                    position: 'absolute',
                    right: '42px',
                    background: 'none',
                    border: 'none',
                    fontSize: '16px',
                    cursor: 'pointer',
                    color: isSpeechSearching ? '#ef4444' : '#64748b',
                    display: 'flex',
                    align-items: 'center',
                    justify-content: 'center'
                  }}
                  title="Voice Search"
                >
                  {isSpeechSearching ? '🎙️' : '🎤'}
                </button>
                <button type="submit" className="search-btn">🔍</button>
              </div>
            </div>

            <div className="form-group">
              <label>Deal Type</label>
              <div className="segmented-control">
                <button
                  type="button"
                  className={adTypeFilter === '' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('')}
                >
                  All
                </button>
                <button
                  type="button"
                  className={adTypeFilter === 'sell' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('sell')}
                >
                  For Sale
                </button>
                <button
                  type="button"
                  className={adTypeFilter === 'rent' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('rent')}
                >
                  For Rent
                </button>
                <button
                  type="button"
                  className={adTypeFilter === 'buy' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('buy')}
                >
                  Wanted
                </button>
              </div>
            </div>

            <div className="form-group">
              <label>Price Range</label>
              <div className="price-inputs">
                <input
                  type="number"
                  placeholder="Min ₹"
                  value={minPrice}
                  onChange={(e) => setMinPrice(e.target.value)}
                  className="input-field price-field"
                />
                <span>to</span>
                <input
                  type="number"
                  placeholder="Max ₹"
                  value={maxPrice}
                  onChange={(e) => setMaxPrice(e.target.value)}
                  className="input-field price-field"
                />
              </div>
            </div>

            {locationCoords && (
              <div className="form-group" style={{ marginTop: '16px' }}>
                <label style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--text-dark)' }}>
                  <span style={{ fontWeight: 'bold' }}>Distance Radius</span>
                  <span style={{ color: 'var(--primary-green)', fontWeight: 'bold' }}>
                    {radius ? `${radius} km` : 'All Kerala'}
                  </span>
                </label>
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginTop: '8px' }}>
                  <input
                    type="range"
                    min="0"
                    max="100"
                    step="5"
                    value={radius || 0}
                    onChange={(e) => {
                      const val = parseInt(e.target.value);
                      setRadius(val === 0 ? '' : val);
                    }}
                    style={{ flex: 1, accentColor: 'var(--primary-green)', cursor: 'pointer' }}
                  />
                </div>
                <small style={{ fontSize: '11px', color: '#64748b', display: 'block', marginTop: '6px' }}>
                  Centred on: {locationActive || 'Detected location'}
                </small>
              </div>
            )}

            <div className="form-group" style={{ marginTop: '16px' }}>
              <label>Sort By</label>
              <select 
                value={sortBy} 
                onChange={(e) => setSortBy(e.target.value)} 
                className="input-field"
                style={{ padding: '10px', borderRadius: '8px', border: '1px solid var(--border-color)', width: '100%', boxSizing: 'border-box' }}
              >
                <option value="">Newest Listings</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
                {locationCoords && (
                  <option value="distance">Nearest First</option>
                )}
              </select>
            </div>

            {/* Dynamic Specific Filters based on Category Selection */}
            {(() => {
              const catId = parseInt(categoryFilter);
              const isCars = catId === 25 || catId === 26;
              const isMobiles = catId === 11 || catId === 12;
              const isProperties = catId === 32 || catId === 33 || catId === 34 || catId === 35 || catId === 36 || catId === 37 || catId === 38;

              if (!isCars && !isMobiles && !isProperties) return null;

              let filterGroups = {};
              if (isCars) {
                filterGroups = {
                  'Popular Brands': ['Maruti Suzuki', 'Hyundai', 'Honda', 'Toyota', 'Tata', 'Mahindra'],
                  'Body Types': ['Sedan', 'SUV', 'Hatchback'],
                  'Fuel Types': ['Petrol', 'Diesel', 'Electric', 'CNG']
                };
              } else if (isMobiles) {
                filterGroups = {
                  'Brands': ['Apple', 'Samsung', 'OnePlus', 'Xiaomi', 'Realme'],
                  'Storage': ['64 GB', '128 GB', '256 GB', '512 GB']
                };
              } else if (isProperties) {
                filterGroups = {
                  'Properties Type': ['House', 'Apartment', 'Villa', 'Plot'],
                  'Size / Layout': ['1 BHK', '2 BHK', '3 BHK', '4 BHK']
                };
              }

              return (
                <div style={{ marginTop: '20px', borderTop: '1px solid var(--border-color)', paddingTop: '16px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                    <span style={{ fontSize: '13px', fontWeight: 'bold', color: 'var(--text-dark)' }}>Category Filters</span>
                    {spec && (
                      <button type="button" onClick={() => setSpec('')} style={{ fontSize: '11px', color: 'var(--primary-green)', background: 'none', border: 'none', cursor: 'pointer', fontWeight: 'bold' }}>
                        Clear
                      </button>
                    )}
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {Object.entries(filterGroups).map(([groupTitle, options]) => (
                      <div key={groupTitle}>
                        <span style={{ fontSize: '10px', fontWeight: '800', color: '#64748b', textTransform: 'uppercase', display: 'block', marginBottom: '6px' }}>{groupTitle}</span>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                          {options.map(opt => {
                            const isSelected = spec === opt;
                            return (
                              <button
                                key={opt}
                                type="button"
                                onClick={() => setSpec(isSelected ? '' : opt)}
                                style={{
                                  padding: '5px 10px',
                                  fontSize: '11px',
                                  fontWeight: '600',
                                  borderRadius: '8px',
                                  border: isSelected ? '1px solid var(--primary-green)' : '1px solid var(--border-color)',
                                  backgroundColor: isSelected ? '#f0fdf4' : '#fff',
                                  color: isSelected ? 'var(--primary-green-dark)' : 'var(--text-dark)',
                                  cursor: 'pointer',
                                  transition: 'all 0.2s ease'
                                }}
                              >
                                {opt}
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}

            <div className="filter-actions">
              <button type="button" onClick={fetchProducts} className="apply-btn">
                Apply Filters
              </button>
              <button type="button" onClick={resetFilters} className="clear-btn">
                Reset
              </button>
            </div>
          </form>
        </aside>

        {/* Listings Section */}
        <section className="listings-section">
          {loading ? (
            <div className="status-container">
              <div className="spinner"></div>
              <p>Fetching amazing local listings...</p>
            </div>
          ) : error ? (
            <div className="status-container error-state">
              <div className="error-icon">⚠️</div>
              <h3>Connection Issues</h3>
              <p>{error}</p>
              <button onClick={fetchProducts} className="retry-btn">Retry Connection</button>
            </div>
          ) : products.length === 0 ? (
            <div className="status-container empty-state">
              <div className="empty-icon">📭</div>
              <h3>No Listings Found</h3>
              <p>We couldn't find any listings matching your active filters. Try broadening your criteria or reset the search.</p>
              <button onClick={resetFilters} className="retry-btn">Clear All Filters</button>
            </div>
          ) : (
            <div className="listings-grid">
              {products.map((product) => (
                <div
                  key={product.id}
                  className="product-card"
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
                        onError={(e) => {
                          e.target.onerror = null;
                          e.target.src = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=400';
                        }}
                      />
                    ) : (
                      <div className="placeholder-image">
                        📦
                      </div>
                    )}
                    <span className={`ad-type-badge ${product.type}`}>
                      {product.type === 'sell' ? 'FOR SALE' : (product.type === 'rent' ? 'FOR RENT' : 'WANTED')}
                    </span>
                  </div>

                  <div className="card-info">
                    <span className="card-category">{product.category_name || 'Marketplace'}</span>
                    <h3 className="card-title">{product.title}</h3>
                    <div className="card-meta">
                      <span className="card-price">{formatPrice(product.price)}</span>
                      <span className="card-date">{formatDate(product.created_at)}</span>
                    </div>
                    {product.location_name && (
                      <div className="card-location" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '4px' }}>
                        <span>📍 {product.location_name.split(',')[0]}</span>
                        {product.distance !== undefined && product.distance !== null && (
                          <span className="card-distance" style={{ fontSize: '11px', color: 'var(--primary-green)', fontWeight: 'bold' }}>
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

      {/* Message Actions Modal */}
      {selectedMessageAction && (
        <div className="modal-overlay" style={{ zIndex: 10000 }} onClick={() => setSelectedMessageAction(null)}>
          <div className="modal-content" style={{ maxWidth: '320px', padding: '24px', textAlign: 'center' }} onClick={(e) => e.stopPropagation()}>
            <h3 style={{ margin: '0 0 16px', fontSize: '18px', fontWeight: 'bold' }}>Message Options</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              <button 
                onClick={() => {
                  const msgId = selectedMessageAction.id;
                  setSelectedMessageAction(null);
                  deleteMessageReact(msgId, 'for_me');
                }} 
                className="btn-secondary" 
                style={{ width: '100%', padding: '12px', borderRadius: '12px', color: '#ef4444', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', cursor: 'pointer', border: '1px solid var(--border-color)', background: '#fff', fontWeight: 'bold' }}
              >
                🗑️ Delete for me
              </button>
              {selectedMessageAction.isMe && (
                <button 
                  onClick={() => {
                    const msgId = selectedMessageAction.id;
                    setSelectedMessageAction(null);
                    deleteMessageReact(msgId, 'for_everyone');
                  }} 
                  className="btn-primary" 
                  style={{ width: '100%', padding: '12px', borderRadius: '12px', background: '#ef4444', borderColor: '#ef4444', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', cursor: 'pointer', fontWeight: 'bold' }}
                >
                  🔄 Delete for everyone
                </button>
              )}
              <button 
                onClick={() => setSelectedMessageAction(null)} 
                className="btn-secondary" 
                style={{ width: '100%', padding: '12px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', border: '1px solid var(--border-color)', background: '#fff', fontWeight: 'bold' }}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Product Detail Modal */}
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
                    onError={(e) => {
                      e.target.onerror = null;
                      e.target.src = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=800';
                    }}
                  />
                ) : (
                  <div className="modal-placeholder-image">📦 No Image Available</div>
                )}
              </div>

              {!chatOpen ? (
                <div className="modal-details">
                  <div className="modal-header-meta">
                    <span className={`ad-type-badge ${selectedProduct.type}`}>
                      {selectedProduct.type === 'sell' ? 'FOR SALE' : (selectedProduct.type === 'rent' ? 'FOR RENT' : 'WANTED')}
                    </span>
                    <span className="modal-id">Ref: {selectedProduct.unique_id || `ID-${selectedProduct.id}`}</span>
                  </div>

                  <h2 className="modal-title">{selectedProduct.title}</h2>
                  <div className="modal-price">{formatPrice(selectedProduct.price)}</div>

                  <div className="modal-seller-card" style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '12px', background: '#f8fafc', borderRadius: '16px', border: '1px solid var(--border-color)', margin: '16px 0' }}>
                    <div style={{ width: '40px', height: '40px', borderRadius: '50%', overflow: 'hidden', background: '#cbd5e1', display: 'flex', alignItems: 'center', justify: 'center' }}>
                      {selectedProduct.seller_picture ? (
                        <img src={`${backendUrl}/${selectedProduct.seller_picture}`} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                      ) : (
                        <span style={{ fontWeight: 'bold', color: '#475569' }}>{selectedProduct.seller_username ? selectedProduct.seller_username.charAt(0).toUpperCase() : 'S'}</span>
                      )}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <span style={{ fontWeight: 'bold', fontSize: '14px', color: 'var(--text-dark)' }}>{selectedProduct.seller_username || 'Seller'}</span>
                        {selectedProduct.seller_verified && (
                          <i className="fa fa-check-circle" style={{ color: '#22c55e', fontSize: '13px' }} title="Verified Seller"></i>
                        )}
                        {(selectedProduct.seller_rating > 0) && (
                          <span style={{ fontSize: '11px', color: '#facc15', fontWeight: 'bold', display: 'inline-flex', alignItems: 'center', gap: '2px' }}>
                            ⭐ {selectedProduct.seller_rating}
                          </span>
                        )}
                      </div>
                      <small style={{ color: '#64748b', fontSize: '11px', display: 'block' }}>
                        {selectedProduct.seller_verified ? 'Verified Seller' : 'Registered Peer'} ({selectedProduct.seller_reviews_count || 0} reviews)
                      </small>
                    </div>
                  </div>

                  <hr className="divider" />

                  <h4 className="details-heading">Description</h4>
                  <p className="modal-desc">{selectedProduct.description || 'No description provided by seller.'}</p>

                  <h4 className="details-heading">Listing Metrics & Information</h4>
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
                    <button className="contact-btn chat-btn" onClick={handleOpenChat}>
                      💬 Chat with Seller
                    </button>
                    {selectedProduct.phone_number && (
                      <a href={`tel:${selectedProduct.phone_number}`} className="contact-btn call-btn">
                        📞 Call Seller ({selectedProduct.phone_number})
                      </a>
                    )}
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
                      style={{ background: '#25d366', color: '#fff', border: '1.5px solid #25d366', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', cursor: 'pointer', fontWeight: 'bold' }}
                      onClick={() => {
                        const shareUrl = window.location.origin + '/product.php?id=' + selectedProduct.id;
                        let text = `*Enteangadi Local listing* 🛍️🌟\n\n`;
                        text += `*Item:* ${selectedProduct.title}\n`;
                        text += `*Price:* ₹${parseFloat(selectedProduct.price).toLocaleString('en-IN')}\n`;
                        text += `*Location:* ${selectedProduct.location_name || 'Kerala'}\n\n`;
                        text += `Nalla smart deal aanu! 🥳 Nokki thiranj vaangan click cheyoo:\n`;
                        text += `${shareUrl}`;
                        window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
                      }}
                    >
                      🟢 Share on WhatsApp
                    </button>
                  </div>
                </div>
              ) : (
                <div className="chat-pane-app">
                  {/* Chat Header */}
                  <div className="chat-pane-header">
                    <button className="chat-back-btn" onClick={() => setChatOpen(false)}>←</button>
                    <div className="chat-user-info">
                      <span className="chat-username">Chat with Seller</span>
                      <span className="chat-status">Online</span>
                    </div>
                  </div>

                  {/* Messages List */}
                  <div className="chat-messages-container" id="react-chat-box">
                    {loadingMessages ? (
                      <div className="chat-loading"><div className="chat-mini-spinner"></div> Loading messages...</div>
                    ) : messages.length === 0 ? (
                      <div className="chat-empty">Start the conversation about {selectedProduct.title}</div>
                    ) : (
                      groupMessages(messages).map(group => {
                        if (group.type === 'image_group') {
                          const lastImageMsg = group.images[group.images.length - 1].msg;
                          const time = new Date(lastImageMsg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                          return (
                            <div key={group.id} className={`chat-bubble-row ${group.isMe ? 'msg-me' : 'msg-other'}`}>
                              <div 
                                className="chat-bubble chat-bubble-images"
                                onMouseDown={() => startLongPress(lastImageMsg.id, group.isMe)}
                                onMouseUp={() => cancelLongPress(lastImageMsg.id)}
                                onMouseMove={() => cancelLongPress(lastImageMsg.id)}
                                onTouchStart={() => startLongPress(lastImageMsg.id, group.isMe)}
                                onTouchEnd={() => cancelLongPress(lastImageMsg.id)}
                                onTouchMove={() => cancelLongPress(lastImageMsg.id)}
                              >
                                {renderImageGroup(group)}
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '4px', marginTop: '4px', opacity: 0.8 }}>
                                  <span className="msg-time" style={{ fontSize: '10px', fontWeight: 600 }}>{time}</span>
                                  {group.isMe && (
                                    lastImageMsg.is_read == 1 ? (
                                      <span className="msg-status seen" style={{ fontSize: '10px', color: '#38bdf8', fontWeight: 'bold' }}>✓✓</span>
                                    ) : lastImageMsg.is_delivered == 1 ? (
                                      <span className="msg-status delivered" style={{ fontSize: '10px', color: 'rgba(255,255,255,0.9)' }}>✓✓</span>
                                    ) : (
                                      <span className="msg-status sent" style={{ fontSize: '10px', color: 'rgba(255,255,255,0.6)' }}>✓</span>
                                    )
                                  )}
                                </div>
                              </div>
                            </div>
                          );
                        } else {
                          const msg = group.msg;
                          const isMe = msg.sender_id == myId;
                          const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                          return (
                            <div key={msg.id} className={`chat-bubble-row ${isMe ? 'msg-me' : 'msg-other'}`}>
                              <div 
                                className="chat-bubble"
                                onMouseDown={() => startLongPress(msg.id, isMe)}
                                onMouseUp={() => cancelLongPress(msg.id)}
                                onMouseMove={() => cancelLongPress(msg.id)}
                                onTouchStart={() => startLongPress(msg.id, isMe)}
                                onTouchEnd={() => cancelLongPress(msg.id)}
                                onTouchMove={() => cancelLongPress(msg.id)}
                              >
                                {msg.message_text.startsWith('[AUDIO]:') ? (
                                  <div className="message-audio-player">
                                    <button type="button" className="react-audio-btn" onClick={(e) => { e.stopPropagation(); playVoiceNote(`${backendUrl}/${msg.message_text.replace('[AUDIO]:', '')}`); }}>
                                      ▶️
                                    </button>
                                    <span className="audio-duration-tag">Voice note</span>
                                  </div>
                                ) : (
                                  <div className="message-text">{msg.message_text}</div>
                                )}
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '4px', marginTop: '4px', opacity: 0.8 }}>
                                  <span className="msg-time" style={{ fontSize: '10px', fontWeight: 600 }}>{time}</span>
                                  {isMe && (
                                    msg.is_read == 1 ? (
                                      <span className="msg-status seen" style={{ fontSize: '10px', color: '#38bdf8', fontWeight: 'bold' }}>✓✓</span>
                                    ) : msg.is_delivered == 1 ? (
                                      <span className="msg-status delivered" style={{ fontSize: '10px', color: 'rgba(255,255,255,0.9)' }}>✓✓</span>
                                    ) : (
                                      <span className="msg-status sent" style={{ fontSize: '10px', color: 'rgba(255,255,255,0.6)' }}>✓</span>
                                    )
                                  )}
                                </div>
                              </div>
                            </div>
                          );
                        }
                      })
                    )}
                  </div>

                  {/* Input & Voice Recording Area */}
                  {selectedProduct.status === 'deleted' ? (
                    <div className="chat-disabled-banner" style={{ background: '#fee2e2', border: '1px solid #fecaca', color: '#991b1b', padding: '16px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', fontWeight: 'bold', fontSize: '0.9rem', width: '100%', boxSizing: 'border-box' }}>
                      ⚠️ This product has been deleted by the seller.
                    </div>
                  ) : (
                    <>
                      {/* Context/Category Quick Replies Chips */}
                      <div className="react-quick-replies" style={{ display: 'flex', gap: '8px', overflowX: 'auto', padding: '8px 12px 12px', background: '#f8fafc', borderBottom: '1px solid var(--border-color)', width: '100%', boxSizing: 'border-box', WebkitOverflowScrolling: 'touch' }}>
                        {getQuickReplies(selectedProduct).map((reply, idx) => (
                          <button
                            key={idx}
                            onClick={() => sendQuickReplyMessage(reply)}
                            style={{ flexShrink: 0, background: '#fff', border: '1px solid var(--border-color)', padding: '6px 12px', borderRadius: '16px', fontSize: '11px', fontWeight: 'bold', color: 'var(--text-dark)', cursor: 'pointer', boxShadow: 'var(--shadow-sm)' }}
                          >
                            {reply}
                          </button>
                        ))}
                      </div>
                      <div className="chat-input-row">
                      {isRecording ? (
                        <div className="recording-status">
                          <span className="recording-dot"></span>
                          <span className="recording-timer">{formatTime(recordingSeconds)}</span>
                          <button type="button" className="btn-discard" onClick={discardRecording}>🗑️</button>
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
                            style={{ background: 'transparent', border: 'none', cursor: 'pointer', fontSize: '1.25rem', padding: '0 8px', display: 'flex', alignItems: 'center' }}
                            title="Send Image"
                          >
                            📷
                          </button>
                          <input
                            type="file"
                            id="react-chat-image-input"
                            accept="image/*"
                            multiple
                            style={{ display: 'none' }}
                            onChange={async (e) => {
                              if (e.target.files && e.target.files.length > 0) {
                                const files = Array.from(e.target.files);
                                for (let i = 0; i < files.length; i++) {
                                  await uploadChatImage(files[i]);
                                }
                                e.target.value = ''; // clear input so the same files can be re-selected
                              }
                            }}
                          />
                        </>
                      )}

                      <button
                        type="button"
                        className={`btn-mic-react ${isRecording ? 'recording-active' : ''}`}
                        onClick={toggleRecording}
                      >
                        {isRecording ? '⏹️' : '🎙️'}
                      </button>

                      {!isRecording && (
                        <button className="btn-send-react" onClick={sendTextMessage}>
                          ✈️
                        </button>
                      )}
                    </div>
                  </>)}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Premium Voice Search modal overlay */}
      {isSpeechSearching && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          width: '100vw',
          height: '100vh',
          background: 'rgba(15, 23, 42, 0.65)',
          backdropFilter: 'blur(8px)',
          WebkitBackdropFilter: 'blur(8px)',
          zIndex: 10000,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontFamily: 'Inter, sans-serif'
        }}>
          <div style={{
            background: 'var(--white, #fff)',
            padding: '40px',
            borderRadius: '24px',
            boxShadow: 'var(--shadow-lg)',
            textAlign: 'center',
            maxWidth: '320px',
            width: '90%'
          }}>
            <div style={{ position: 'relative', width: '90px', height: '90px', margin: '0 auto 24px' }}>
              <div style={{
                position: 'absolute',
                width: '100%',
                height: '100%',
                background: 'rgba(46, 125, 50, 0.2)',
                borderRadius: '50%',
                animation: 'voicePulse 2s infinite'
              }}></div>
              <div style={{
                position: 'absolute',
                width: '100%',
                height: '100%',
                background: 'rgba(46, 125, 50, 0.15)',
                borderRadius: '50%',
                animation: 'voicePulse 2s infinite 0.6s'
              }}></div>
              <div style={{
                position: 'absolute',
                width: '70px',
                height: '70px',
                top: '10px',
                left: '10px',
                background: 'var(--primary-green)',
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'white',
                fontSize: '28px',
                boxShadow: '0 4px 10px rgba(46,125,50,0.3)'
              }}>
                🎤
              </div>
            </div>
            <h3 style={{ margin: '0 0 8px 0', fontSize: '18px', fontWeight: '800', color: 'var(--text-dark)' }}>
              {speechStatusText || (speechError
                ? ((localStorage.getItem('enteangadi_lang') || 'en') === 'ml' ? 'തടസ്സം നേരിട്ടു' : 'Failed to record')
                : ((localStorage.getItem('enteangadi_lang') || 'en') === 'ml' ? 'കേൾക്കുന്നു...' : 'Listening...'))}
            </h3>
            <p style={{ margin: '0 0 24px 0', fontSize: '13px', color: speechError ? '#ef4444' : 'var(--text-muted)' }}>
              {speechError || ((localStorage.getItem('enteangadi_lang') || 'en') === 'ml' ? 'സംസാരിക്കൂ...' : 'Speak now...')}
            </p>
            <button
              type="button"
              onClick={() => {
                if (window.activeReactMediaRecorder && window.activeReactMediaRecorder.state === 'recording') {
                  window.activeReactMediaRecorder.stop();
                } else if (window.activeSpeechRecognition) {
                  window.activeSpeechRecognition.stop();
                }
              }}
              style={{
                background: 'var(--primary-green)',
                border: 'none',
                color: 'white',
                padding: '10px 24px',
                borderRadius: '12px',
                fontWeight: '700',
                fontSize: '13px',
                cursor: 'pointer',
                marginRight: '8px'
              }}
            >
              Done
            </button>
            <button
              type="button"
              onClick={() => {
                setIsSpeechSearching(false);
                setSpeechError('');
                setSpeechStatusText('');
                if (window.activeSpeechRecognition) {
                  window.activeSpeechRecognition.abort();
                }
                if (window.activeReactMediaRecorder && window.activeReactMediaRecorder.state === 'recording') {
                  window.activeReactMediaRecorder.onstop = null; // abort upload
                  window.activeReactMediaRecorder.stop();
                }
              }}
              style={{
                background: '#f1f5f9',
                border: 'none',
                color: '#475569',
                padding: '10px 24px',
                borderRadius: '12px',
                fontWeight: '700',
                fontSize: '13px',
                cursor: 'pointer'
              }}
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* Fullscreen Chat Image Lightbox / Slideshow */}
      {lightboxOpen && (
        <div className="lightbox-overlay" onClick={() => setLightboxOpen(false)}>
          <button className="lightbox-close" onClick={() => setLightboxOpen(false)}>×</button>

          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            {lightboxImages.length > 1 && (
              <button
                className="lightbox-nav-btn prev"
                onClick={() => setLightboxIndex((prev) => (prev - 1 + lightboxImages.length) % lightboxImages.length)}
              >
                ‹
              </button>
            )}

            <div className="lightbox-image-container">
              <img
                src={lightboxImages[lightboxIndex]}
                alt={`Full Screen View ${lightboxIndex + 1}`}
                className="lightbox-main-image"
              />
            </div>

            {lightboxImages.length > 1 && (
              <button
                className="lightbox-nav-btn next"
                onClick={() => setLightboxIndex((prev) => (prev + 1) % lightboxImages.length)}
              >
                ›
              </button>
            )}

            <div className="lightbox-counter">
              {lightboxIndex + 1} / {lightboxImages.length}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default App;
