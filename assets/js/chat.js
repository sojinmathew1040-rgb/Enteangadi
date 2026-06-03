/**
 * Enteangadi - Real-time Chat Logic
 * Manages message fetching, custom audio player rendering, native MediaRecorder interactions, and auto-polling.
 */

let isFirstLoad = true;
let lastMessageCount = 0;
let currentPlayingAudio = null;
let currentPlayingBtn = null;

// Lightbox states for web chat
let lightboxOpen = false;
let lightboxImages = [];
let lightboxIndex = 0;

// MediaRecorder state variables
let mediaRecorder = null;
let audioChunks = [];
let recordingTimerInterval = null;
let recordingSeconds = 0;
let isRecording = false;

// Group consecutive images from the same sender sent within 60 seconds of each other
function groupMessages(msgs) {
    const grouped = [];
    for (let i = 0; i < msgs.length; i++) {
        const msg = msgs[i];
        const isImage = msg.message_text && msg.message_text.startsWith('[IMAGE]:');

        if (isImage) {
            const imageUrl = msg.message_text.replace('[IMAGE]:', '');
            const isMe = typeof myId !== 'undefined' && msg.sender_id == myId;
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
}

// Open fullscreen Lightbox slideshow
function openLightbox(imagesUrls, index) {
    lightboxImages = imagesUrls;
    lightboxIndex = index;
    lightboxOpen = true;

    let overlay = document.getElementById('lightbox-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'lightbox-overlay';
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = `
            <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
            <div class="lightbox-content" onclick="event.stopPropagation()">
                <button class="lightbox-nav-btn prev" onclick="navigateLightbox(-1)">&lsaquo;</button>
                <div class="lightbox-image-container">
                    <img id="lightbox-image" class="lightbox-main-image" src="" alt="Lightbox View">
                </div>
                <button class="lightbox-nav-btn next" onclick="navigateLightbox(1)">&rsaquo;</button>
                <div id="lightbox-counter" class="lightbox-counter"></div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', closeLightbox);
    }

    updateLightboxContent();
    overlay.style.display = 'flex';
}

function closeLightbox() {
    lightboxOpen = false;
    const overlay = document.getElementById('lightbox-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function navigateLightbox(dir) {
    if (!lightboxImages.length) return;
    lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
    updateLightboxContent();
}

function updateLightboxContent() {
    const img = document.getElementById('lightbox-image');
    const counter = document.getElementById('lightbox-counter');
    const prevBtn = document.querySelector('.lightbox-overlay .lightbox-nav-btn.prev');
    const nextBtn = document.querySelector('.lightbox-overlay .lightbox-nav-btn.next');

    if (img) {
        img.src = lightboxImages[lightboxIndex];
    }
    if (counter) {
        counter.innerText = `${lightboxIndex + 1} / ${lightboxImages.length}`;
    }
    if (prevBtn && nextBtn) {
        const displayStyle = lightboxImages.length > 1 ? 'flex' : 'none';
        prevBtn.style.display = displayStyle;
        nextBtn.style.display = displayStyle;
    }
}

// Keydown handler for keyboard navigation
document.addEventListener('keydown', (e) => {
    if (!lightboxOpen) return;
    if (e.key === 'ArrowLeft') {
        navigateLightbox(-1);
    } else if (e.key === 'ArrowRight') {
        navigateLightbox(1);
    } else if (e.key === 'Escape') {
        closeLightbox();
    }
});

// Build WhatsApp-style HTML layout for the image group
function renderImageGroupHTML(group) {
    const count = group.images.length;
    const imgUrls = group.images.map(img => `${EnteangadiConfig.baseUrl}/${img.url}`);
    const imgUrlsJSON = JSON.stringify(imgUrls).replace(/"/g, '&quot;');

    if (count === 1) {
        const img = group.images[0];
        const imgUrl = `${EnteangadiConfig.baseUrl}/${img.url}`;
        return `
            <div class="chat-image-single" onclick="openLightbox(${imgUrlsJSON}, 0)">
                <img src="${imgUrl}" class="message-chat-image" alt="Shared Photo">
            </div>
        `;
    }

    if (count === 2) {
        return `
            <div class="chat-image-grid grid-2">
                <div class="grid-item" onclick="openLightbox(${imgUrlsJSON}, 0)">
                    <img src="${EnteangadiConfig.baseUrl}/${group.images[0].url}" alt="Shared Photo 1">
                </div>
                <div class="grid-item" onclick="openLightbox(${imgUrlsJSON}, 1)">
                    <img src="${EnteangadiConfig.baseUrl}/${group.images[1].url}" alt="Shared Photo 2">
                </div>
            </div>
        `;
    }

    if (count === 3) {
        return `
            <div class="chat-image-grid grid-3">
                <div class="grid-left" onclick="openLightbox(${imgUrlsJSON}, 0)">
                    <img src="${EnteangadiConfig.baseUrl}/${group.images[0].url}" alt="Shared Photo 1">
                </div>
                <div class="grid-right">
                    <div class="grid-sub-item" onclick="openLightbox(${imgUrlsJSON}, 1)">
                        <img src="${EnteangadiConfig.baseUrl}/${group.images[1].url}" alt="Shared Photo 2">
                    </div>
                    <div class="grid-sub-item" onclick="openLightbox(${imgUrlsJSON}, 2)">
                        <img src="${EnteangadiConfig.baseUrl}/${group.images[2].url}" alt="Shared Photo 3">
                    </div>
                </div>
            </div>
        `;
    }

    // 4 or more photos
    const displayImages = group.images.slice(0, 4);
    const remaining = count - 3;

    let gridHtml = '<div class="chat-image-grid grid-4">';
    displayImages.forEach((img, idx) => {
        const isLast = idx === 3;
        const imgUrl = `${EnteangadiConfig.baseUrl}/${img.url}`;
        gridHtml += `
            <div class="grid-item" onclick="openLightbox(${imgUrlsJSON}, ${idx})">
                <img src="${imgUrl}" alt="Shared Photo ${idx + 1}">
                ${isLast && remaining > 1 ? `
                    <div class="grid-overlay">
                        <span>+${remaining}</span>
                    </div>
                ` : ''}
            </div>
        `;
    });
    gridHtml += '</div>';
    return gridHtml;
}

function fetchMessages() {
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    fetch(`api_chat.php?action=fetch&other_id=${otherId}&product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
            }
        });
}

function renderMessages(messages) {
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    if (messages.length === 0 && isFirstLoad) {
        chatBox.innerHTML = `
            <div class="empty-chat-state">
                <div class="empty-chat-icon"><i class="fa fa-comments"></i></div>
                <h3>Start the conversation</h3>
                <p>Be the first to send a message about this listing.</p>
            </div>
        `;
        isFirstLoad = false;
        return;
    }

    // Trigger native/web notifications for incoming new messages
    if (!isFirstLoad && messages.length > lastMessageCount) {
        const newMessages = messages.slice(lastMessageCount);
        newMessages.forEach(msg => {
            const isMe = typeof myId !== 'undefined' && msg.sender_id == myId;
            if (!isMe && window.EnteangadiMobile && typeof window.EnteangadiMobile.showLocalNotification === 'function') {
                window.EnteangadiMobile.showLocalNotification(msg.sender_name || 'Buyer/Seller', msg.message_text);
            }
        });
    }

    lastMessageCount = messages.length;

    let html = '';
    const groups = groupMessages(messages);
    groups.forEach((group) => {
        if (group.type === 'image_group') {
            const isMe = group.isMe;
            const lastImageMsg = group.images[group.images.length - 1].msg;
            const time = new Date(lastImageMsg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            html += `
                <div class="message-row ${isMe ? 'msg-me' : 'msg-other'}">
                    <div class="message-bubble message-bubble-images">
                        ${renderImageGroupHTML(group)}
                        <div class="message-meta">
                            <span class="msg-time">${time}</span>
                            ${isMe ? '<i class="fa fa-check-double msg-status"></i>' : ''}
                        </div>
                    </div>
                </div>
            `;
        } else {
            const msg = group.msg;
            const isMe = typeof myId !== 'undefined' && msg.sender_id == myId;
            const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            let messageContent = '';
            if (msg.message_text.startsWith('[AUDIO]:')) {
                const audioPath = msg.message_text.replace('[AUDIO]:', '');
                const audioFullUrl = `${EnteangadiConfig.baseUrl}/${audioPath}`;
                messageContent = `
                    <div class="message-audio-player">
                        <button type="button" class="audio-play-btn" onclick="toggleAudioPlayback(this, '${audioFullUrl}')">
                            <i class="fa fa-play"></i>
                        </button>
                        <div class="audio-waveform-wrapper">
                            <div class="audio-track-wave"></div>
                            <div class="audio-progress-wave"></div>
                        </div>
                        <span class="audio-duration-tag">Voice note</span>
                    </div>
                `;
            } else {
                messageContent = `<div class="message-text">${escapeHtml(msg.message_text)}</div>`;
            }

            html += `
                <div class="message-row ${isMe ? 'msg-me' : 'msg-other'}">
                    <div class="message-bubble">
                        ${messageContent}
                        <div class="message-meta">
                            <span class="msg-time">${time}</span>
                            ${isMe ? '<i class="fa fa-check-double msg-status"></i>' : ''}
                        </div>
                    </div>
                </div>
            `;
        }
    });

    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 150;

    if (chatBox.innerHTML !== html || isFirstLoad) {
        chatBox.innerHTML = html;
        if (isAtBottom || isFirstLoad) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
        isFirstLoad = false;
    }
}

// Inline Custom Audio Player Playback controller
function toggleAudioPlayback(btn, url) {
    const icon = btn.querySelector('i');

    // Toggle play/pause if this audio is already active
    if (currentPlayingAudio && currentPlayingBtn === btn) {
        if (currentPlayingAudio.paused) {
            currentPlayingAudio.play();
            icon.className = 'fa fa-pause';
        } else {
            currentPlayingAudio.pause();
            icon.className = 'fa fa-play';
        }
        return;
    }

    // Stop any existing playing voice notes
    if (currentPlayingAudio) {
        currentPlayingAudio.pause();
        if (currentPlayingBtn) {
            currentPlayingBtn.querySelector('i').className = 'fa fa-play';
        }
    }

    // Start playing new voice note
    const audio = new Audio(url);
    currentPlayingAudio = audio;
    currentPlayingBtn = btn;

    audio.play();
    icon.className = 'fa fa-pause';

    audio.addEventListener('ended', () => {
        icon.className = 'fa fa-play';
        currentPlayingAudio = null;
        currentPlayingBtn = null;
    });
}

function sendMessage() {
    const messageInput = document.getElementById('message-input');
    const chatBox = document.getElementById('chat-box');
    if (!messageInput || !chatBox) return;

    const text = messageInput.value.trim();
    if (!text) return;

    messageInput.value = '';
    updateInputButtons();
    messageInput.focus();

    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', otherId);
    formData.append('product_id', productId);
    formData.append('message', text);

    fetch('api_chat.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
                setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 100);
            }
        });
}

// Start and stop voice recording
async function toggleVoiceRecording() {
    const micBtn = document.getElementById('micBtn');
    const sendBtn = document.getElementById('sendBtn');
    const msgInput = document.getElementById('message-input');
    const recStatus = document.getElementById('recording-status');
    const timerEl = recStatus?.querySelector('.recording-timer');

    if (!micBtn || !msgInput || !recStatus) return;

    if (!isRecording) {
        try {
            // Trigger native mobile OS permission requests if in WebView wrapper
            if (window.EnteangadiMobile && window.EnteangadiMobile.isRunningInMobile()) {
                await window.EnteangadiMobile.requestAllPermissions();
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("SECURE_CONTEXT_REQUIRED");
            }

            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };

            mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                if (audioChunks.length > 0 && recordingSeconds > 0) {
                    await uploadAudioMessage(audioBlob);
                }
                stream.getTracks().forEach(track => track.stop()); // shut down microhardware
            };

            mediaRecorder.start();
            isRecording = true;

            // Adjust input UI to show recording pulses
            msgInput.style.display = 'none';
            sendBtn.style.display = 'none';
            recStatus.style.display = 'flex';
            micBtn.innerHTML = '<i class="fa fa-stop"></i>';
            micBtn.classList.add('recording-active');
            micBtn.title = "Stop and Send";

            // Initialize counter
            recordingSeconds = 0;
            if (timerEl) timerEl.innerText = '00:00';
            recordingTimerInterval = setInterval(() => {
                recordingSeconds++;
                const mins = String(Math.floor(recordingSeconds / 60)).padStart(2, '0');
                const secs = String(recordingSeconds % 60).padStart(2, '0');
                if (timerEl) timerEl.innerText = `${mins}:${secs}`;
            }, 1000);

        } catch (err) {
            console.error("Microphone capture failed:", err);
            if (err.message === "SECURE_CONTEXT_REQUIRED" || err.name === "TypeError") {
                alert("Microphone access requires a secure context (HTTPS or localhost).\n\nTo test voice recording on your phone, run 'adb reverse tcp:80 tcp:80' and configure your Capacitor config to load from 'http://localhost/Enteangadi'.");
            } else {
                alert("Microphone permission is required to record voice notes.");
            }
        }
    } else {
        stopVoiceRecording(true);
    }
}

function stopVoiceRecording(shouldSend) {
    if (!isRecording) return;

    clearInterval(recordingTimerInterval);
    isRecording = false;

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        if (!shouldSend) {
            audioChunks = []; // clear chunks
        }
        mediaRecorder.stop();
    }

    const micBtn = document.getElementById('micBtn');
    const sendBtn = document.getElementById('sendBtn');
    const msgInput = document.getElementById('message-input');
    const recStatus = document.getElementById('recording-status');

    if (msgInput) msgInput.style.display = 'block';
    if (recStatus) recStatus.style.display = 'none';
    if (micBtn) {
        micBtn.innerHTML = '<i class="fa fa-microphone"></i>';
        micBtn.classList.remove('recording-active');
        micBtn.title = "Record Voice Note";
    }
    updateInputButtons();
}

async function uploadAudioMessage(blob) {
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        const tempRow = document.createElement('div');
        tempRow.className = 'message-row msg-me temp-sending-audio';
        tempRow.innerHTML = `
            <div class="message-bubble" style="opacity: 0.7;">
                <div class="message-text"><i class="fa fa-spinner fa-spin"></i> Sending voice note...</div>
            </div>
        `;
        chatBox.appendChild(tempRow);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    const formData = new FormData();
    formData.append('action', 'send_audio');
    formData.append('receiver_id', otherId);
    formData.append('product_id', productId);
    formData.append('audio_data', blob);

    try {
        const response = await fetch('api_chat.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            fetchMessages();
        } else {
            alert("Failed to upload audio message.");
            fetchMessages();
        }
    } catch (e) {
        console.error("Audio note upload failed:", e);
        fetchMessages();
    }
}

async function uploadChatImage(file) {
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;
    if (!file) return;

    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        const tempRow = document.createElement('div');
        tempRow.className = 'message-row msg-me temp-sending-image';
        tempRow.innerHTML = `
            <div class="message-bubble" style="opacity: 0.7;">
                <div class="message-text"><i class="fa fa-spinner fa-spin"></i> Sending photo...</div>
            </div>
        `;
        chatBox.appendChild(tempRow);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    const formData = new FormData();
    formData.append('action', 'send_image');
    formData.append('receiver_id', otherId);
    formData.append('product_id', productId);
    formData.append('image_data', file);

    try {
        const response = await fetch('api_chat.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            fetchMessages();
        } else {
            alert(result.error || "Failed to upload image.");
            fetchMessages();
        }
    } catch (e) {
        console.error("Image upload failed:", e);
        fetchMessages();
    }
}

function escapeHtml(unsafe) {
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function deleteChat() {
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    if (confirm("Permanently delete this conversation?")) {
        const formData = new FormData();
        formData.append('action', 'delete_chat');
        formData.append('other_id', otherId);
        formData.append('product_id', productId);
        fetch('api_chat.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "inbox.php";
                }
            });
    }
}

// Toggle send/mic buttons based on input text content
function updateInputButtons() {
    const messageInput = document.getElementById('message-input');
    const micBtn = document.getElementById('micBtn');
    const sendBtn = document.getElementById('sendBtn');
    if (!messageInput || !micBtn || !sendBtn) return;

    if (messageInput.value.trim() === '') {
        sendBtn.style.display = 'none';
        micBtn.style.display = 'flex';
    } else {
        sendBtn.style.display = 'flex';
        micBtn.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
        messageInput.addEventListener('input', updateInputButtons);
    }

    // Bind recording buttons
    const micBtn = document.getElementById('micBtn');
    if (micBtn) {
        micBtn.addEventListener('click', toggleVoiceRecording);
    }

    const cancelRecBtn = document.getElementById('cancelRecBtn');
    if (cancelRecBtn) {
        cancelRecBtn.addEventListener('click', () => stopVoiceRecording(false));
    }

    updateInputButtons();
    fetchMessages();
    setInterval(fetchMessages, 3000);
});
