<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Bot, Loader2, MessageSquare, Plus, Send, User } from 'lucide-vue-next';
import { nextTick, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
}

interface Chat {
    id: string;
    title: string;
    message_count: number;
    updated_at: string;
}

const props = defineProps<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
    chats: Chat[];
}>();

const conversations = ref<Chat[]>(props.chats || []);
const currentChatId = ref<string | null>(null);
const isLoading = ref(false);
const messagesContainerRef = ref<HTMLElement | null>(null);

const form = useForm<{
    message: string;
    conversation_id: string | null;
}>({
    message: '',
    conversation_id: null,
});

const currentMessages = ref<Message[]>([]);

const scrollToBottom = async () => {
    await nextTick();

    if (messagesContainerRef.value) {
        messagesContainerRef.value.scrollTop =
            messagesContainerRef.value.scrollHeight;
    }
};

const startNewConversation = () => {
    currentChatId.value = null;
    currentMessages.value = [];
    form.conversation_id = null;
};

const loadConversation = async (chatId: string) => {
    currentChatId.value = chatId;
    form.conversation_id = chatId;
    isLoading.value = true;

    try {
        const response = await fetch('chat/' + chatId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN':
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] || '',
            },
        });

        if (response.ok) {
            const data = await response.json();
            currentMessages.value = data.messages.map((msg: any) => ({
                id: msg.id,
                role: msg.role,
                content: msg.content,
                timestamp: new Date(msg.created_at),
            }));
        }
    } catch (error) {
        console.error('Error loading conversation:', error);
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
};

const sendMessage = async () => {
    if (!form.message.trim() || isLoading.value) {
        return;
    }

    const userMessage: Message = {
        id: Date.now().toString(),
        role: 'user',
        content: form.message.trim(),
        timestamp: new Date(),
    };

    currentMessages.value.push(userMessage);
    const userMessageContent = form.message;
    form.message = '';
    isLoading.value = true;

    await scrollToBottom();

    try {
        const response = await fetch('chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN':
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] || '',
            },
            body: JSON.stringify({
                message: userMessageContent,
                conversation_id: form.conversation_id,
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to send message');
        }

        const data = await response.json();

        const assistantMessage: Message = {
            id: data.conversation_id + '-assistant',
            role: 'assistant',
            content: data.response,
            timestamp: new Date(),
        };

        currentMessages.value.push(assistantMessage);

        // Update or create conversation
        if (!currentChatId.value) {
            currentChatId.value = data.conversation_id;
            form.conversation_id = data.conversation_id;

            const newConversation: Chat = {
                id: data.conversation_id,
                title:
                    userMessageContent.slice(0, 50) +
                    (userMessageContent.length > 50 ? '...' : ''),
                message_count: 2,
                updated_at: new Date().toISOString(),
            };
            conversations.value.unshift(newConversation);
        } else {
            const convIndex = conversations.value.findIndex(
                (c) => c.id === currentChatId.value,
            );

            if (convIndex !== -1) {
                conversations.value[convIndex].message_count += 2;
                conversations.value[convIndex].updated_at =
                    new Date().toISOString();
            }
        }
    } catch (error) {
        console.error('Error sending message:', error);
        const errorMessage: Message = {
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: 'Sorry, I encountered an error. Please try again.',
            timestamp: new Date(),
        };
        currentMessages.value.push(errorMessage);
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
};

const handleKeyPress = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <Head title="AI Chat" />

    <AppLayout>
        <div class="flex h-[calc(100vh-4rem)]">
            <!-- Sidebar - Conversations List -->
            <aside
                class="hidden w-72 flex-col border-r border-sidebar-border/70 bg-sidebar md:flex"
            >
                <div
                    class="flex h-14 items-center gap-2 border-b border-sidebar-border/70 px-4"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        @click="startNewConversation"
                        class="w-full justify-start gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        New Chat
                    </Button>
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    <div
                        v-if="conversations.length === 0"
                        class="px-2 py-4 text-sm text-muted-foreground"
                    >
                        <div class="flex items-center gap-2">
                            <MessageSquare class="h-4 w-4" />
                            <span>No conversations yet</span>
                        </div>
                        <p class="mt-1 text-xs">Start a new chat to begin</p>
                    </div>
                    <div v-else class="space-y-1">
                        <Button
                            v-for="conv in conversations"
                            :key="conv.id"
                            variant="ghost"
                            size="sm"
                            @click="loadConversation(conv.id)"
                            :class="[
                                'w-full justify-start gap-2 text-left font-normal',
                                currentChatId === conv.id ? 'bg-accent' : '',
                            ]"
                        >
                            <MessageSquare class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ conv.title }}</span>
                        </Button>
                    </div>
                </div>
            </aside>

            <!-- Main Chat Area -->
            <main class="flex flex-1 flex-col">
                <!-- Messages Container -->
                <div ref="messagesContainerRef" class="flex-1 overflow-y-auto">
                    <div
                        v-if="currentMessages.length === 0"
                        class="flex h-full flex-col items-center justify-center p-8 text-center"
                    >
                        <div
                            class="mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10"
                        >
                            <Bot class="h-8 w-8 text-primary" />
                        </div>
                        <h2 class="text-2xl font-semibold">
                            How can I help you today?
                        </h2>
                        <p class="mt-2 max-w-md text-muted-foreground">
                            I'm your AI assistant. Ask me anything, and I'll do
                            my best to provide helpful information.
                        </p>
                        <div
                            class="mt-8 grid w-full max-w-2xl grid-cols-1 gap-3 sm:grid-cols-2"
                        >
                            <Button
                                variant="outline"
                                @click="
                                    form.message =
                                        'Explain quantum computing in simple terms';
                                    sendMessage();
                                "
                                class="h-auto py-3 text-left"
                            >
                                <span class="line-clamp-2"
                                    >Explain quantum computing in simple
                                    terms</span
                                >
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    form.message =
                                        'What are the benefits of meditation?';
                                    sendMessage();
                                "
                                class="h-auto py-3 text-left"
                            >
                                <span class="line-clamp-2"
                                    >What are the benefits of meditation?</span
                                >
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    form.message =
                                        'Help me write a professional email';
                                    sendMessage();
                                "
                                class="h-auto py-3 text-left"
                            >
                                <span class="line-clamp-2"
                                    >Help me write a professional email</span
                                >
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    form.message = 'What is machine learning?';
                                    sendMessage();
                                "
                                class="h-auto py-3 text-left"
                            >
                                <span class="line-clamp-2"
                                    >What is machine learning?</span
                                >
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col">
                        <div
                            v-for="message in currentMessages"
                            :key="message.id"
                            class="border-b border-sidebar-border/30 p-6"
                        >
                            <div class="mx-auto flex max-w-4xl gap-4">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        message.role === 'user'
                                            ? 'bg-primary/10'
                                            : 'bg-secondary'
                                    "
                                >
                                    <User
                                        v-if="message.role === 'user'"
                                        class="h-5 w-5 text-primary"
                                    />
                                    <Bot
                                        v-else
                                        class="h-5 w-5 text-secondary-foreground"
                                    />
                                </div>
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{{
                                            message.role === 'user'
                                                ? 'You'
                                                : 'Химчик AI'
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                message.timestamp.toLocaleTimeString()
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="text-sm leading-relaxed whitespace-pre-wrap"
                                    >
                                        {{ message.content }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Indicator -->
                        <div
                            v-if="isLoading"
                            class="border-b border-sidebar-border/30 p-6"
                        >
                            <div class="mx-auto flex max-w-4xl gap-4">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-secondary"
                                >
                                    <Loader2
                                        class="h-5 w-5 animate-spin text-secondary-foreground"
                                    />
                                </div>
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold"
                                            >AI Assistant</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-1 text-sm text-muted-foreground"
                                    >
                                        <span
                                            class="h-2 w-2 animate-bounce rounded-full bg-current"
                                            style="animation-delay: 0ms"
                                        />
                                        <span
                                            class="h-2 w-2 animate-bounce rounded-full bg-current"
                                            style="animation-delay: 150ms"
                                        />
                                        <span
                                            class="h-2 w-2 animate-bounce rounded-full bg-current"
                                            style="animation-delay: 300ms"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div
                    class="border-t border-sidebar-border/70 bg-background p-4"
                >
                    <div class="mx-auto flex max-w-4xl items-end gap-2">
                        <div class="relative flex-1">
                            <textarea
                                v-model="form.message"
                                @keydown="handleKeyPress"
                                placeholder="Type your message..."
                                rows="1"
                                class="flex max-h-48 w-full resize-none overflow-y-auto rounded-md border border-input bg-transparent px-4 py-3 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                style="min-height: 48px"
                                :disabled="isLoading"
                            />
                        </div>
                        <Button
                            @click="sendMessage"
                            :disabled="!form.message.trim() || isLoading"
                            size="icon"
                        >
                            <Send v-if="!isLoading" class="h-4 w-4" />
                            <Loader2 v-else class="h-4 w-4 animate-spin" />
                        </Button>
                    </div>
                    <p class="mt-2 text-center text-xs text-muted-foreground">
                        AI can make mistakes. Consider checking important
                        information.
                    </p>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
