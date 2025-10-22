<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { marked } from 'marked'

const props = defineProps({
  review: Object,
  prDetails: Object
})

const chatMessages = ref([])
const chatInput = ref('')
const chatLoading = ref(false)

const sendChatMessage = async () => {
  if (!chatInput.value.trim() || !props.review) return

  const userMessage = {
    role: 'user',
    content: chatInput.value,
    timestamp: new Date().toISOString()
  }

  chatMessages.value.push(userMessage)
  const question = chatInput.value
  chatInput.value = ''
  chatLoading.value = true

  try {
    const response = await axios.post('/api/chat', {
      pr: props.prDetails,
      review: props.review.content,
      messages: chatMessages.value,
      question: question
    })

    const aiMessage = {
      role: 'assistant',
      content: response.data.response,
      timestamp: new Date().toISOString()
    }

    chatMessages.value.push(aiMessage)
  } catch (err) {
    const errorMessage = {
      role: 'error',
      content: 'Failed to get response: ' + (err.response?.data?.error || err.message),
      timestamp: new Date().toISOString()
    }
    chatMessages.value.push(errorMessage)
  } finally {
    chatLoading.value = false
  }
}

const clearChat = () => {
  if (confirm('Clear all chat messages?')) {
    chatMessages.value = []
  }
}

marked.setOptions({
  breaks: true,
  gfm: true
})
</script>

<template>
  <div class="chat-container">
    <div class="chat-header">
      <div class="chat-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        Ask questions about this review
      </div>
      <button v-if="chatMessages.length > 0" class="btn-clear-chat" @click="clearChat">
        Clear Chat
      </button>
    </div>

    <div class="chat-messages" ref="chatMessagesContainer">
      <div v-if="chatMessages.length === 0" class="chat-empty">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <p>Start a conversation about this code review</p>
        <p class="chat-empty-hint">Ask questions like "Can you explain this issue in more detail?" or "How should I implement this fix?"</p>
      </div>

      <div
        v-for="(message, index) in chatMessages"
        :key="index"
        class="chat-message"
        :class="'chat-message-' + message.role"
      >
        <div class="chat-message-avatar">
          <svg v-if="message.role === 'user'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <svg v-else-if="message.role === 'assistant'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
          </svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <div class="chat-message-content">
          <div class="chat-message-header">
            <span class="chat-message-role">{{ message.role === 'user' ? 'You' : message.role === 'assistant' ? 'AI' : 'Error' }}</span>
            <span class="chat-message-time">{{ new Date(message.timestamp).toLocaleTimeString() }}</span>
          </div>
          <div class="chat-message-text" v-html="marked.parse(message.content)"></div>
        </div>
      </div>

      <div v-if="chatLoading" class="chat-message chat-message-assistant">
        <div class="chat-message-avatar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
          </svg>
        </div>
        <div class="chat-message-content">
          <div class="chat-typing">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </div>
    </div>

    <div class="chat-input-container">
      <textarea
        v-model="chatInput"
        class="chat-input"
        placeholder="Ask a question about this review..."
        rows="3"
        @keydown.enter.prevent="!chatLoading && chatInput.trim() && sendChatMessage()"
        :disabled="chatLoading"
      ></textarea>
      <button
        class="btn-send-chat"
        @click="sendChatMessage"
        :disabled="chatLoading || !chatInput.trim()"
      >
        <svg v-if="!chatLoading" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"></line>
          <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
        <span v-if="!chatLoading">Send</span>
        <span v-else>Sending...</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--bg-card);
  border-radius: 12px;
  border: 2px solid var(--border);
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chat-header {
  padding: 1.25rem 1.5rem;
  background: var(--bg-hover);
  border-bottom: 2px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chat-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
}

.chat-title svg {
  color: var(--primary);
}

.btn-clear-chat {
  padding: 0.5rem 1rem;
  background: #fef2f2;
  color: #dc2626;
  border: 2px solid #fecaca;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-clear-chat:hover {
  background: #fee2e2;
  transform: translateY(-1px);
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.chat-messages::-webkit-scrollbar {
  width: 8px;
}

.chat-messages::-webkit-scrollbar-track {
  background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 4px;
}

.chat-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--text-muted);
  gap: 1rem;
  padding: 3rem;
}

.chat-empty svg {
  color: var(--primary);
  opacity: 0.5;
}

.chat-empty p {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.chat-empty-hint {
  font-size: 0.875rem !important;
  font-weight: 400 !important;
  opacity: 0.8;
  max-width: 400px;
}

.chat-message {
  display: flex;
  gap: 1rem;
  animation: messageSlideIn 0.3s ease;
}

@keyframes messageSlideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-message-user {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.chat-message-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 2px 8px var(--glow);
}

.chat-message-user .chat-message-avatar {
  background: linear-gradient(135deg, var(--secondary), #059669);
}

.chat-message-error .chat-message-avatar {
  background: linear-gradient(135deg, #ef4444, #dc2626);
}

.chat-message-content {
  flex: 1;
  max-width: 75%;
}

.chat-message-user .chat-message-content {
  text-align: right;
}

.chat-message-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.chat-message-user .chat-message-header {
  justify-content: flex-end;
  flex-direction: row-reverse;
}

.chat-message-role {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
}

.chat-message-time {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.chat-message-text {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border-radius: 14px;
  font-size: 0.95rem;
  line-height: 1.7;
  color: var(--text-secondary);
  border: 2px solid var(--border);
}

.chat-message-user .chat-message-text {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(16, 185, 129, 0.08));
  border-color: var(--primary-light);
  text-align: left;
}

.chat-message-error .chat-message-text {
  background: rgba(239, 68, 68, 0.1);
  border-color: #fecaca;
  color: #dc2626;
}

.chat-message-text :deep(p) {
  margin: 0.5rem 0;
}

.chat-message-text :deep(p:first-child) {
  margin-top: 0;
}

.chat-message-text :deep(p:last-child) {
  margin-bottom: 0;
}

.chat-message-text :deep(code) {
  background: var(--bg-tertiary);
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-size: 0.875em;
}

.chat-message-text :deep(pre) {
  background: var(--bg-tertiary);
  padding: 1rem;
  border-radius: 8px;
  overflow-x: auto;
  margin: 0.75rem 0;
}

.chat-typing {
  padding: 1rem 1.25rem;
  background: var(--bg-hover);
  border-radius: 14px;
  border: 2px solid var(--border);
  display: flex;
  gap: 0.5rem;
  width: fit-content;
}

.chat-typing span {
  width: 8px;
  height: 8px;
  background: var(--primary);
  border-radius: 50%;
  animation: typingBounce 1.4s infinite;
}

.chat-typing span:nth-child(2) {
  animation-delay: 0.2s;
}

.chat-typing span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typingBounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 1;
  }
  30% {
    transform: translateY(-10px);
    opacity: 0.7;
  }
}

.chat-input-container {
  padding: 1.25rem 1.5rem;
  background: var(--bg-hover);
  border-top: 2px solid var(--border);
  display: flex;
  gap: 1rem;
  align-items: flex-end;
}

.chat-input {
  flex: 1;
  padding: 0.75rem 1rem;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: 0.95rem;
  font-family: inherit;
  line-height: 1.5;
  resize: none;
  transition: all 0.2s;
}

.chat-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.chat-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.chat-input::placeholder {
  color: var(--text-muted);
}

.btn-send-chat {
  padding: 0.75rem 1.5rem;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 2px 8px var(--glow);
  white-space: nowrap;
}

.btn-send-chat:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px var(--glow);
}

.btn-send-chat:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
</style>
