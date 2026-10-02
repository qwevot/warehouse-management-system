<?php
// AI Chatbot Right Side-Panel & Floating Action Button Component (Lazy Loaded & Live AI Connected)
?>
<!-- AI Chatbot Floating Trigger Button -->
<button type="button" id="aiChatToggleBtn" class="ai-chat-fab" aria-label="AI Assistant" onclick="toggleAiChat()" title="AI Assistant">
    <span class="ai-chat-fab-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
    </span>
</button>

<!-- Lazy Template: Instantiated into DOM only on first open/click -->
<template id="aiChatDrawerTemplate">
    <aside id="aiChatDrawer" class="ai-chat-drawer" aria-hidden="true" role="complementary" aria-labelledby="aiChatTitle">
        <!-- Header -->
        <div class="ai-chat-header">
            <div class="ai-chat-header-info">
                <div class="ai-chat-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <h3 id="aiChatTitle" class="ai-chat-title">AI Assistant</h3>
            </div>
            <div class="ai-chat-header-actions">
                <button type="button" id="aiChatTtsToggleBtn" class="ai-chat-header-btn" onclick="toggleAiTtsAudio()" title="Suara AI (Text to Speech): Mati" aria-label="Toggle AI Speech">
                    <svg class="ai-tts-icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" style="display: none;">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                    <svg class="ai-tts-icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <line x1="23" y1="9" x2="17" y2="15"></line>
                        <line x1="17" y1="9" x2="23" y2="15"></line>
                    </svg>
                </button>
                <button type="button" class="ai-chat-header-btn" onclick="clearAiChatDemo()" title="Clear chat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                </button>
                <button type="button" class="ai-chat-header-btn ai-chat-close-btn" onclick="closeAiChat()" title="Close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="aiChatMessages" class="ai-chat-body">
            <div class="ai-chat-stream" id="aiChatStream">
                <div class="ai-message ai-message-agent">
                    <div class="ai-msg-avatar">AI</div>
                    <div class="ai-msg-content">
                        <div class="ai-msg-text">
                            Halo! Ada yang bisa saya bantu terkait stok atau data inventaris gudang hari ini?
                        </div>
                        <div class="ai-msg-actions">
                            <button type="button" class="ai-msg-speak-btn" onclick="handleSpeakButtonClick(this, 'Halo! Ada yang bisa saya bantu terkait stok atau data inventaris gudang hari ini?')" title="Dengarkan salam ini">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                                </svg> Baca
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ai-chat-suggestions" id="aiChatSuggestions">
                <div class="ai-prompt-chips">
                    <button type="button" class="ai-chip" onclick="handleAiPromptChip('Barang apa saja yang stoknya hampir habis?')">
                        Barang apa saja yang stoknya hampir habis?
                    </button>
                    <button type="button" class="ai-chip" onclick="handleAiPromptChip('Berapa total unit barang yang tersedia saat ini?')">
                        Berapa total unit barang yang tersedia saat ini?
                    </button>
                    <button type="button" class="ai-chip" onclick="handleAiPromptChip('Tampilkan daftar brand yang terdaftar')">
                        Tampilkan daftar brand yang terdaftar
                    </button>
                </div>
            </div>
        </div>

        <!-- Input Footer -->
        <div class="ai-chat-footer">
            <form id="aiChatForm" class="ai-chat-form" onsubmit="handleAiChatSubmit(event)">
                <div class="ai-input-wrapper">
                    <input 
                        type="text" 
                        id="aiChatInput" 
                        class="ai-chat-input" 
                        placeholder="Tanya atau bicara tentang inventaris..." 
                        autocomplete="off"
                    />
                    <div class="ai-input-actions">
                        <button type="button" id="aiChatMicBtn" class="ai-chat-mic-btn" aria-label="Voice input" title="Bicara (Speech to Text)" onclick="toggleAiVoiceRecording()">
                            <svg class="ai-mic-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                <line x1="12" y1="19" x2="12" y2="23"></line>
                                <line x1="8" y1="23" x2="16" y2="23"></line>
                            </svg>
                        </button>
                        <button type="submit" id="aiChatSendBtn" class="ai-chat-send-btn" aria-label="Send" title="Send">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="aiVoiceRecordingStatus" class="ai-voice-status" style="display: none;">
                    <span class="ai-voice-pulse-dot"></span>
                    <span id="aiVoiceStatusText">Merekam suara... Silakan bicara</span>
                </div>
            </form>
        </div>
    </aside>
</template>

<!-- AI Chatbot Lazy Execution Engine & Live API System -->
<script>
(function() {
    "use strict";

    const STORAGE_KEY = "wms_ai_sidebar_open";
    let isInitialized = false;
    let chatHistory = [];
    let isRequestInProgress = false;

    function lazyInitAiChat() {
        if (isInitialized || document.getElementById("aiChatDrawer")) {
            isInitialized = true;
            return true;
        }

        const tpl = document.getElementById("aiChatDrawerTemplate");
        if (!tpl) return false;

        const clone = tpl.content.cloneNode(true);
        document.body.appendChild(clone);
        isInitialized = true;
        setupMobileKeyboardAdaptation();
        initTtsState();
        return true;
    }

    function setupMobileKeyboardAdaptation() {
        const input = document.getElementById("aiChatInput");
        const messages = document.getElementById("aiChatMessages");

        if (input && messages) {
            input.addEventListener("focus", () => {
                setTimeout(() => {
                    messages.scrollTop = messages.scrollHeight;
                }, 300);
            });
        }

        if (window.visualViewport) {
            const updateLayoutOnKeyboard = () => {
                const drawer = document.getElementById("aiChatDrawer");
                if (!drawer || !document.body.classList.contains("ai-chat-open")) return;

                if (window.innerWidth <= 900) {
                    const vv = window.visualViewport;
                    const vh = vv.height;
                    const offsetTop = vv.offsetTop;

                    drawer.style.height = `${vh}px`;
                    drawer.style.top = `${offsetTop}px`;
                    drawer.style.bottom = 'auto';

                    if (messages) {
                        messages.scrollTop = messages.scrollHeight;
                    }
                } else {
                    drawer.style.height = '';
                    drawer.style.top = '';
                    drawer.style.bottom = '';
                }
            };

            window.visualViewport.addEventListener("resize", updateLayoutOnKeyboard);
            window.visualViewport.addEventListener("scroll", updateLayoutOnKeyboard);
        }
    }

    function openAiChat() {
        lazyInitAiChat();

        const drawer = document.getElementById("aiChatDrawer");
        const fab = document.getElementById("aiChatToggleBtn");
        const input = document.getElementById("aiChatInput");

        document.body.classList.add("ai-chat-open");
        if (drawer) {
            drawer.classList.add("open");
            drawer.setAttribute("aria-hidden", "false");
        }
        if (fab) {
            fab.classList.add("active");
            fab.setAttribute("title", "Close AI Assistant");
        }

        try {
            localStorage.setItem(STORAGE_KEY, "1");
        } catch (e) {}

        setTimeout(() => {
            if (input && !isRequestInProgress) input.focus();
        }, 150);
    }

    function closeAiChat() {
        const drawer = document.getElementById("aiChatDrawer");
        const fab = document.getElementById("aiChatToggleBtn");

        document.body.classList.remove("ai-chat-open");
        if (drawer) {
            drawer.classList.remove("open");
            drawer.setAttribute("aria-hidden", "true");
            drawer.style.height = '';
            drawer.style.top = '';
            drawer.style.bottom = '';
        }
        if (fab) {
            fab.classList.remove("active");
            fab.setAttribute("title", "AI Assistant");
        }

        try {
            localStorage.setItem(STORAGE_KEY, "0");
        } catch (e) {}
    }

    function toggleAiChat() {
        if (document.body.classList.contains("ai-chat-open")) {
            closeAiChat();
        } else {
            openAiChat();
        }
    }

    function handleAiPromptChip(text) {
        lazyInitAiChat();
        const input = document.getElementById("aiChatInput");
        if (input && !isRequestInProgress) {
            input.value = text;
            handleAiChatSubmit();
        }
    }

    // Full Markdown (GFM) & LaTeX (KaTeX) Processor
    function renderAiMarkdownAndLatex(rawText) {
        if (!rawText) return "";

        let text = String(rawText);
        const mathBlocks = [];

        // 1. Extract Display Math $$...$$ or \[...\]
        text = text.replace(/\$\$([\s\S]+?)\$\$/g, function(match, math) {
            const token = `__AI_KATEX_DISPLAY_${mathBlocks.length}__`;
            let rendered = match;
            try {
                if (window.katex) {
                    rendered = window.katex.renderToString(math.trim(), { displayMode: true, throwOnError: false });
                }
            } catch (e) {}
            mathBlocks.push({ token: token, html: rendered });
            return '\n\n' + token + '\n\n';
        });

        text = text.replace(/\\\[([\s\S]+?)\\\]/g, function(match, math) {
            const token = `__AI_KATEX_DISPLAY_${mathBlocks.length}__`;
            let rendered = match;
            try {
                if (window.katex) {
                    rendered = window.katex.renderToString(math.trim(), { displayMode: true, throwOnError: false });
                }
            } catch (e) {}
            mathBlocks.push({ token: token, html: rendered });
            return '\n\n' + token + '\n\n';
        });

        // 2. Extract Inline Math $...$ or \(...\)
        text = text.replace(/(?<!\\)\$([^\$\n]+?)(?<!\\)\$/g, function(match, math) {
            const token = `__AI_KATEX_INLINE_${mathBlocks.length}__`;
            let rendered = match;
            try {
                if (window.katex) {
                    rendered = window.katex.renderToString(math.trim(), { displayMode: false, throwOnError: false });
                }
            } catch (e) {}
            mathBlocks.push({ token: token, html: rendered });
            return token;
        });

        text = text.replace(/\\\(([\s\S]+?)\\\)/g, function(match, math) {
            const token = `__AI_KATEX_INLINE_${mathBlocks.length}__`;
            let rendered = match;
            try {
                if (window.katex) {
                    rendered = window.katex.renderToString(math.trim(), { displayMode: false, throwOnError: false });
                }
            } catch (e) {}
            mathBlocks.push({ token: token, html: rendered });
            return token;
        });

        // 3. Process Markdown with Marked.js
        let parsedHtml = "";
        if (window.marked) {
            try {
                window.marked.setOptions({
                    gfm: true,
                    breaks: true,
                    headerIds: false,
                    mangle: false
                });
                parsedHtml = window.marked.parse(text);
            } catch (err) {
                parsedHtml = escapeHtml(text).replace(/\n/g, '<br>');
            }
        } else {
            parsedHtml = escapeHtml(text).replace(/\n/g, '<br>');
        }

        // 4. Restore LaTeX Blocks
        for (let i = 0; i < mathBlocks.length; i++) {
            parsedHtml = parsedHtml.split(mathBlocks[i].token).join(mathBlocks[i].html);
        }

        return parsedHtml;
    }

    // Extract Active Page & UI State Context
    function getActivePageContext() {
        const ctx = {
            page_url: window.location.pathname + window.location.search,
            page_title: window.PAGE_TITLE || document.title,
            active_nav: window.ACTIVE_NAV || '',
            active_modal: null,
            filter_search: null
        };

        // Detect open modal data (e.g. item details modal, checkin modal, etc.)
        const activeModalEl = document.querySelector(".modal.active, .modal.open, #itemModal");
        if (activeModalEl && (activeModalEl.classList.contains("active") || activeModalEl.classList.contains("open") || activeModalEl.style.display === "block" || activeModalEl.style.display === "flex")) {
            const detailName = document.querySelector("#detailName")?.textContent?.trim();
            const detailSerial = document.querySelector("#detailSerial")?.textContent?.trim();
            const detailLocation = document.querySelector("#detailLocation")?.textContent?.trim();
            const detailQty = document.querySelector("#detailQty")?.textContent?.trim();
            const detailStatus = document.querySelector("#detailStatus")?.textContent?.trim();

            if (detailName || detailSerial) {
                ctx.active_modal = {
                    modal_name: "Item Detail Modal",
                    item_name: detailName || null,
                    serial_lot: detailSerial || null,
                    location: detailLocation || null,
                    quantity: detailQty || null,
                    status: detailStatus || null
                };
            }
        }

        // Detect active search/filter text on page
        const searchInput = document.querySelector("input[type='search'], input[name='search'], input[name='q'], #filterSearch, #itemSearch");
        if (searchInput && searchInput.value.trim()) {
            ctx.filter_search = searchInput.value.trim();
        }

        return ctx;
    }

    async function handleAiChatSubmit(e) {
        if (e) e.preventDefault();
        if (isRequestInProgress) return;

        const input = document.getElementById("aiChatInput");
        const sendBtn = document.getElementById("aiChatSendBtn");
        const stream = document.getElementById("aiChatStream");
        const messages = document.getElementById("aiChatMessages");
        const suggestions = document.getElementById("aiChatSuggestions");

        if (!input || !stream) return;

        const message = input.value.trim();
        if (!message) return;

        // Hide suggestions shortcut card after prompt is sent
        if (suggestions) {
            suggestions.style.display = "none";
        }

        isRequestInProgress = true;
        input.value = "";
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;

        // 1. Render User Message
        const userMsgHtml = `
            <div class="ai-message ai-message-user">
                <div class="ai-msg-content">
                    <div class="ai-msg-text">${escapeHtml(message)}</div>
                </div>
            </div>
        `;
        stream.insertAdjacentHTML('beforeend', userMsgHtml);
        if (messages) messages.scrollTop = messages.scrollHeight;

        // 2. Render Typing Indicator
        const typingId = 'aiTyping_' + Date.now();
        const typingHtml = `
            <div class="ai-message ai-message-agent" id="${typingId}">
                <div class="ai-msg-avatar">AI</div>
                <div class="ai-msg-content">
                    <div class="ai-msg-typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        `;
        stream.insertAdjacentHTML('beforeend', typingHtml);
        if (messages) messages.scrollTop = messages.scrollHeight;

        // 3. Dispatch Live API Request with Context
        const baseUrl = (window.BASE_URL || window.BASE || '/Inventory').replace(/\/+$/, "");
        const endpoint = baseUrl + '/actions/ai-chat.php';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    message: message,
                    history: chatHistory.slice(-10), // Limit to last 10 turns to avoid excessive context
                    context: getActivePageContext()
                })
            });

            const typingEl = document.getElementById(typingId);
            if (typingEl) typingEl.remove();

            let result = null;
            try {
                result = await response.json();
            } catch (parseErr) {
                console.error('[AI Assistant] JSON Parse Error:', parseErr);
            }

            if (result && result.success && result.data) {
                const replyText = result.data.reply || "Tidak ada respon dari sistem.";
                const formattedHtml = renderAiMarkdownAndLatex(replyText);
                const msgId = 'aiMsg_' + Date.now();

                const agentMsgHtml = `
                    <div class="ai-message ai-message-agent" id="${msgId}">
                        <div class="ai-msg-avatar">AI</div>
                        <div class="ai-msg-content">
                            <div class="ai-msg-text">${formattedHtml}</div>
                            <div class="ai-msg-actions">
                                <button type="button" class="ai-msg-speak-btn" onclick="handleSpeakButtonClick(this, ${escapeHtml(JSON.stringify(replyText))})" title="Dengarkan jawaban ini">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                                    </svg> Baca
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                stream.insertAdjacentHTML('beforeend', agentMsgHtml);

                // Auto-speak if TTS toggle is active
                if (isTtsEnabled) {
                    const currentMsgEl = document.getElementById(msgId);
                    const speakBtn = currentMsgEl ? currentMsgEl.querySelector(".ai-msg-speak-btn") : null;
                    speakAiResponse(replyText, speakBtn);
                }

                // Update memory history
                chatHistory.push({ role: 'user', content: message });
                chatHistory.push({ role: 'assistant', content: replyText });
            } else {
                let errMsg = (result && result.message) ? result.message : ('Gagal menghubungi server AI (Status ' + response.status + ').');
                if (errMsg.includes('Request too large') || errMsg.includes('tokens per minute') || errMsg.includes('TPM') || errMsg.includes('rate limit')) {
                    errMsg = 'Maaf, kuota permintaan sementara sedang padat. Silakan coba sesaat lagi.';
                }

                const errorMsgHtml = `
                    <div class="ai-message ai-message-agent">
                        <div class="ai-msg-avatar">AI</div>
                        <div class="ai-msg-content">
                            <div class="ai-msg-text" style="color: var(--red-ink, #b91c1c); background: var(--red-soft, #fef2f2); border-color: #fecaca;">
                                ${escapeHtml(errMsg)}
                            </div>
                        </div>
                    </div>
                `;
                stream.insertAdjacentHTML('beforeend', errorMsgHtml);
            }
        } catch (err) {
            console.error('[AI Assistant] Network/Runtime Error:', err);
            const typingEl = document.getElementById(typingId);
            if (typingEl) typingEl.remove();

            const netErrorHtml = `
                <div class="ai-message ai-message-agent">
                    <div class="ai-msg-avatar">AI</div>
                    <div class="ai-msg-content">
                        <div class="ai-msg-text" style="color: var(--red-ink, #b91c1c); background: var(--red-soft, #fef2f2); border-color: #fecaca;">
                            Maaf, terjadi kendala koneksi ke server. Silakan periksa jaringan dan coba kembali.
                        </div>
                    </div>
                </div>
            `;
            stream.insertAdjacentHTML('beforeend', netErrorHtml);
        } finally {
            if (messages) messages.scrollTop = messages.scrollHeight;

            // Reset input and cooldown protection
            input.disabled = false;
            setTimeout(() => {
                isRequestInProgress = false;
                if (sendBtn) sendBtn.disabled = false;
                input.focus();
            }, 800);
        }
    }

    function clearAiChatDemo() {
        chatHistory = [];
        const stream = document.getElementById("aiChatStream");
        const suggestions = document.getElementById("aiChatSuggestions");

        if (suggestions) {
            suggestions.style.display = "";
        }

        if (stream) {
            stream.innerHTML = `
                <div class="ai-message ai-message-agent">
                    <div class="ai-msg-avatar">AI</div>
                    <div class="ai-msg-content">
                        <div class="ai-msg-text">
                            Halo! Ada yang bisa saya bantu terkait stok atau data inventaris gudang hari ini?
                        </div>
                    </div>
                </div>
            `;
        }
    }

    // Speech-to-Text (STT) Whisper Integration
    let mediaRecorder = null;
    let audioChunks = [];
    let isRecording = false;

    async function toggleAiVoiceRecording() {
        lazyInitAiChat();
        if (isRecording) {
            stopVoiceRecording();
        } else {
            startVoiceRecording();
        }
    }

    async function startVoiceRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Peramban Anda belum mendukung fitur perekaman audio mikrofon.');
            return;
        }

        const micBtn = document.getElementById("aiChatMicBtn");
        const statusEl = document.getElementById("aiVoiceRecordingStatus");
        const statusText = document.getElementById("aiVoiceStatusText");
        const input = document.getElementById("aiChatInput");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            audioChunks = [];

            // Determine optimal MIME type supported by browser
            let mimeType = 'audio/webm';
            if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                mimeType = 'audio/ogg;codecs=opus';
            } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            }

            mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});

            mediaRecorder.ondataavailable = function(e) {
                if (e.data && e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.onstop = async function() {
                // Stop audio tracks to release microphone
                stream.getTracks().forEach(track => track.stop());

                const audioBlob = new Blob(audioChunks, { type: mimeType || 'audio/webm' });
                if (audioBlob.size < 500) {
                    resetVoiceRecordingUI();
                    return;
                }

                await sendAudioToWhisper(audioBlob);
            };

            mediaRecorder.start(250);
            isRecording = true;

            if (micBtn) micBtn.classList.add("recording");
            if (statusEl) statusEl.style.display = "flex";
            if (statusText) statusText.textContent = "Mendengarkan... Bicara sekarang (tekan mic lagi jika selesai)";
            if (input) input.placeholder = "Sedang merekam suara Anda...";

        } catch (err) {
            console.error('[AI Assistant STT] Mic access error:', err);
            alert('Izin akses mikrofon ditolak atau mikrofon tidak terdeteksi. Harap periksa pengaturan izin peramban Anda.');
            resetVoiceRecordingUI();
        }
    }

    function stopVoiceRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            const statusText = document.getElementById("aiVoiceStatusText");
            if (statusText) statusText.textContent = "Mengonversi suara ke teks...";
            mediaRecorder.stop();
        }
        isRecording = false;
        const micBtn = document.getElementById("aiChatMicBtn");
        if (micBtn) micBtn.classList.remove("recording");
    }

    function resetVoiceRecordingUI() {
        isRecording = false;
        const micBtn = document.getElementById("aiChatMicBtn");
        const statusEl = document.getElementById("aiVoiceRecordingStatus");
        const input = document.getElementById("aiChatInput");

        if (micBtn) micBtn.classList.remove("recording");
        if (statusEl) statusEl.style.display = "none";
        if (input) input.placeholder = "Tanya atau bicara tentang inventaris...";
    }

    async function sendAudioToWhisper(audioBlob) {
        const baseUrl = (window.BASE_URL || window.BASE || '/Inventory').replace(/\/+$/, "");
        const endpoint = baseUrl + '/actions/ai-stt.php';
        const input = document.getElementById("aiChatInput");

        try {
            const formData = new FormData();
            formData.append('audio', audioBlob, 'voice_recording.webm');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            let result = null;
            try {
                result = await response.json();
            } catch (pErr) {
                console.error('[AI Assistant STT] JSON parse error:', pErr);
            }

            if (result && result.success && result.data && result.data.text) {
                const transcribedText = result.data.text.trim();
                if (input) {
                    input.value = transcribedText;
                    input.focus();
                }
            } else {
                const errMsg = (result && result.message) ? result.message : 'Gagal mentranskripsi suara.';
                console.warn('[AI Assistant STT] Whisper notice:', errMsg);
            }
        } catch (err) {
            console.error('[AI Assistant STT] Network error:', err);
        } finally {
            resetVoiceRecordingUI();
        }
    }

    // Text-to-Speech (TTS) ElevenLabs Integration
    const TTS_STORAGE_KEY = "wms_ai_tts_enabled";
    let isTtsEnabled = false;
    let currentAudioPlayer = null;

    function initTtsState() {
        try {
            isTtsEnabled = localStorage.getItem(TTS_STORAGE_KEY) === "1";
        } catch (e) {
            isTtsEnabled = false;
        }
        updateTtsToggleUI();
    }

    function toggleAiTtsAudio() {
        lazyInitAiChat();
        isTtsEnabled = !isTtsEnabled;
        try {
            localStorage.setItem(TTS_STORAGE_KEY, isTtsEnabled ? "1" : "0");
        } catch (e) {}
        updateTtsToggleUI();

        if (!isTtsEnabled && currentAudioPlayer) {
            currentAudioPlayer.pause();
            currentAudioPlayer = null;
        }
    }

    function updateTtsToggleUI() {
        const btn = document.getElementById("aiChatTtsToggleBtn");
        if (!btn) return;
        const iconOn = btn.querySelector(".ai-tts-icon-on");
        const iconOff = btn.querySelector(".ai-tts-icon-off");

        if (isTtsEnabled) {
            btn.classList.add("active");
            btn.title = "Suara AI (Text to Speech): Aktif";
            if (iconOn) iconOn.style.display = "";
            if (iconOff) iconOff.style.display = "none";
        } else {
            btn.classList.remove("active");
            btn.title = "Suara AI (Text to Speech): Mati";
            if (iconOn) iconOn.style.display = "none";
            if (iconOff) iconOff.style.display = "";
        }
    }

    // Web Audio API Context and Playback Management
    let sharedAudioCtx = null;
    let currentSourceNode = null;
    let activeSpeakBtn = null;
    let activeAbortController = null;

    function getAudioContext() {
        if (!sharedAudioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                sharedAudioCtx = new AudioContextClass();
            }
        }
        if (sharedAudioCtx && sharedAudioCtx.state === 'suspended') {
            sharedAudioCtx.resume();
        }
        return sharedAudioCtx;
    }

    function stopCurrentSpeech() {
        if (activeAbortController) {
            try { activeAbortController.abort(); } catch (e) {}
            activeAbortController = null;
        }
        if (currentSourceNode) {
            try {
                currentSourceNode.onended = null;
                currentSourceNode.stop(0);
                currentSourceNode.disconnect();
            } catch (e) {}
            currentSourceNode = null;
        }
        if (currentAudioPlayer) {
            try {
                currentAudioPlayer.pause();
                currentAudioPlayer.currentTime = 0;
            } catch (e) {}
            currentAudioPlayer = null;
        }
        if (window.speechSynthesis) {
            try { window.speechSynthesis.cancel(); } catch (e) {}
        }
        if (activeSpeakBtn) {
            activeSpeakBtn.classList.remove("playing");
            activeSpeakBtn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                </svg> Baca
            `;
            activeSpeakBtn = null;
        }
        document.querySelectorAll(".ai-msg-speak-btn.playing").forEach(b => {
            b.classList.remove("playing");
            b.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                </svg> Baca
            `;
        });
    }

    async function speakAiResponse(rawText, buttonEl) {
        if (!rawText) return;

        // 1. Immediately unlock browser AudioContext in the user gesture call stack
        const audioCtx = getAudioContext();

        // 2. Stop any existing playback
        stopCurrentSpeech();

        // 3. Mark button active immediately
        if (buttonEl) {
            activeSpeakBtn = buttonEl;
            buttonEl.classList.add("playing");
            buttonEl.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <rect x="6" y="4" width="4" height="16"></rect>
                    <rect x="14" y="4" width="4" height="16"></rect>
                </svg> Hentikan
            `;
        }

        const baseUrl = (window.BASE_URL || window.BASE || '/Inventory').replace(/\/+$/, "");
        const endpoint = baseUrl + '/actions/ai-tts.php';
        activeAbortController = new AbortController();

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'audio/mpeg, application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ text: rawText }),
                signal: activeAbortController.signal
            });

            const contentType = response.headers.get("content-type") || "";

            if (contentType.includes("audio/mpeg") || contentType.includes("audio/")) {
                const arrayBuffer = await response.arrayBuffer();

                if (audioCtx) {
                    const audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
                    const source = audioCtx.createBufferSource();
                    source.buffer = audioBuffer;
                    source.connect(audioCtx.destination);

                    source.onended = () => {
                        if (currentSourceNode === source) {
                            stopCurrentSpeech();
                        }
                    };

                    currentSourceNode = source;
                    source.start(0);
                } else {
                    // Fallback to HTML5 audio element with Blob
                    const blob = new Blob([arrayBuffer], { type: 'audio/mpeg' });
                    const blobUrl = URL.createObjectURL(blob);
                    const audio = new Audio(blobUrl);
                    currentAudioPlayer = audio;
                    audio.onended = () => { stopCurrentSpeech(); };
                    audio.onerror = () => { stopCurrentSpeech(); };
                    await audio.play();
                }
            } else {
                let errMsg = "Terjadi kendala pada sintesis suara.";
                try {
                    const errRes = await response.json();
                    if (errRes && errRes.message) errMsg = errRes.message;
                } catch (_) {}
                console.warn('[AI Assistant TTS] Notice from server:', errMsg);

                // Fallback to Web Speech Synthesis API
                if (window.speechSynthesis) {
                    const cleanText = rawText.replace(/[*_#`\[\]\(\)]/g, '');
                    const utterance = new SpeechSynthesisUtterance(cleanText);
                    utterance.lang = 'id-ID';
                    utterance.onend = () => { stopCurrentSpeech(); };
                    utterance.onerror = () => { stopCurrentSpeech(); };
                    window.speechSynthesis.speak(utterance);
                } else {
                    stopCurrentSpeech();
                }
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('[AI Assistant TTS] Playback failed:', err);
            }
            stopCurrentSpeech();
        }
    }

    function handleSpeakButtonClick(btn, text) {
        if (btn && btn.classList.contains("playing")) {
            stopCurrentSpeech();
        } else {
            speakAiResponse(text, btn);
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Expose methods to global scope
    window.lazyInitAiChat = lazyInitAiChat;
    window.openAiChat = openAiChat;
    window.closeAiChat = closeAiChat;
    window.toggleAiChat = toggleAiChat;
    window.handleAiPromptChip = handleAiPromptChip;
    window.handleAiChatSubmit = handleAiChatSubmit;
    window.clearAiChatDemo = clearAiChatDemo;
    window.toggleAiVoiceRecording = toggleAiVoiceRecording;
    window.toggleAiTtsAudio = toggleAiTtsAudio;
    window.speakAiResponse = speakAiResponse;
    window.handleSpeakButtonClick = handleSpeakButtonClick;
})();
</script>
