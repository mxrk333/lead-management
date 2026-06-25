<?php
// This is a floating widget that can be included on any page
// Include this in your main layout/footer
?>

<style>
    /* Floating Chatbot Widget */
    .chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .chat-bubble-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
        position: relative;
    }

    .chat-bubble-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 30px rgba(102, 126, 234, 0.6);
    }

    .chat-bubble-btn.active {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }

    .chat-container {
        position: absolute;
        bottom: 90px;
        right: 0;
        width: 400px;
        max-width: calc(100vw - 20px);
        height: 600px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        opacity: 0;
        transform: scale(0.8) translateY(20px);
        pointer-events: none;
        transition: all 0.3s ease;
    }

    .chat-container.active {
        opacity: 1;
        transform: scale(1) translateY(0);
        pointer-events: all;
    }

    .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-header h3 {
        margin: 0;
        font-size: 1.1rem;
    }

    .chat-header p {
        margin: 5px 0 0 0;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .close-chat {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
        background: #f8f9fa;
    }

    .message {
        display: flex;
        gap: 10px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.user {
        justify-content: flex-end;
    }

    .message-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 12px;
        line-height: 1.5;
        font-size: 0.9rem;
    }

    .message.bot .message-bubble {
        background: #e9ecef;
        color: #333;
        border-radius: 12px 12px 12px 0;
    }

    .message.user .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 12px;
    }

    .input-area {
        padding: 15px;
        border-top: 1px solid #eee;
        display: flex;
        gap: 10px;
    }

    .chat-input {
        flex: 1;
        border: 1px solid #ddd;
        border-radius: 25px;
        padding: 10px 15px;
        font-size: 0.9rem;
        outline: none;
        transition: all 0.3s ease;
    }

    .chat-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .send-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .send-btn:hover {
        transform: scale(1.05);
    }

    .loading-dots {
        display: flex;
        gap: 4px;
        align-items: center;
    }

    .loading-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #667eea;
        animation: bounce 1.4s infinite;
    }

    .loading-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .loading-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes bounce {
        0%, 80%, 100% {
            transform: translateY(0);
            opacity: 0.7;
        }
        40% {
            transform: translateY(-8px);
            opacity: 1;
        }
    }

    .quick-suggestions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 10px;
    }

    .suggestion-btn {
        background: white;
        border: 1px solid #ddd;
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        text-align: left;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        color: #333;
    }

    .suggestion-btn:hover {
        background: #f0f0f0;
        border-color: #667eea;
    }

    @media (max-width: 768px) {
        .chat-container {
            width: calc(100vw - 40px);
            height: 500px;
            bottom: 80px;
            right: -20px;
        }
    }
</style>

<!-- Floating Chat Widget HTML -->
<div class="chat-widget">
    <button class="chat-bubble-btn" id="chatBubbleBtn" title="SPARC BOT Chat">
        <i class="fas fa-comments"></i>
    </button>

    <div class="chat-container" id="chatContainer">
        <div class="chat-header">
            <div>
                <h3><i class="fas fa-robot"></i> SPARC BOT</h3>
                <p>Ask me anything about the system</p>
                <p style="font-size:0.9rem;color:#999;margin-top:6px;">I use internal docs and memos to answer questions smarter.</p>
            </div>
            <button class="close-chat" id="closeChatBtn" aria-label="Close chat">×</button>
        </div>

        <div class="messages-area" id="messagesArea">
            <div class="message bot">
                <div class="message-bubble">
                    👋 Hi! I'm SPARC BOT. I can help you with:
                    <div class="quick-suggestions">
                        <button class="suggestion-btn" onclick="sendMessage('Who is the top performing agent?')">⭐ Top performing agent</button>
                        <button class="suggestion-btn" onclick="sendMessage('Show me team performance')">👥 Team performance</button>
                        <button class="suggestion-btn" onclick="sendMessage('How do I add a new lead?')">❓ How to use the system</button>
                        <button class="suggestion-btn" onclick="sendMessage('Create a monthly report')">📊 Monthly report</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="input-area">
            <input 
                type="text" 
                class="chat-input" 
                id="chatInput" 
                placeholder="Type your question..."
                autocomplete="off"
            >
            <button class="send-btn" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const chatBubbleBtn = document.getElementById('chatBubbleBtn');
    const chatContainer = document.getElementById('chatContainer');
    const closeChatBtn = document.getElementById('closeChatBtn');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const messagesArea = document.getElementById('messagesArea');

    // Toggle chat
    chatBubbleBtn.addEventListener('click', () => {
        chatContainer.classList.toggle('active');
        chatBubbleBtn.classList.toggle('active');
        if (chatContainer.classList.contains('active')) {
            chatInput.focus();
        }
    });

    // Close chat
    closeChatBtn.addEventListener('click', () => {
        chatContainer.classList.remove('active');
        chatBubbleBtn.classList.remove('active');
    });

    // Send message
    sendBtn.addEventListener('click', () => sendMessage());
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && chatInput.value.trim()) {
            sendMessage();
        }
    });

    function sendMessage(text = null) {
        const message = text || chatInput.value.trim();
        if (!message) return;

        // Add user message
        addMessage(message, 'user');
        chatInput.value = '';

        // Show loading indicator
        addMessage('<div class="loading-dots"><span></span><span></span><span></span></div>', 'bot');

        // Send to API
        fetch('api/chatbot-handler.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading indicator
            messagesArea.removeChild(messagesArea.lastChild);
            
            if (data.success) {
                addMessage(data.response, 'bot');
            } else {
                addMessage('Sorry, I encountered an error: ' + (data.error || 'Unknown error'), 'bot');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (messagesArea.lastChild) {
                messagesArea.removeChild(messagesArea.lastChild);
            }
            addMessage('Sorry, something went wrong. Please try again.', 'bot');
        });
    }

    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        bubbleDiv.innerHTML = text;
        
        messageDiv.appendChild(bubbleDiv);
        messagesArea.appendChild(messageDiv);
        
        // Scroll to bottom
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
</script>
