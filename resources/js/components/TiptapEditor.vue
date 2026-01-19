<template>
    <div class="tiptap-editor">
        <div v-if="editor" class="border border-gray-600 rounded-lg overflow-hidden">
            <!-- Toolbar -->
            <div class="bg-gray-700 border-b border-gray-600 p-2 flex flex-wrap gap-1">
                <button
                    type="button"
                    @click="editor.chain().focus().toggleBold().run()"
                    :class="{ 'bg-gray-600': editor.isActive('bold') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Bold"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleItalic().run()"
                    :class="{ 'bg-gray-600': editor.isActive('italic') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Italic"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleStrike().run()"
                    :class="{ 'bg-gray-600': editor.isActive('strike') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Strikethrough"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4H9a3 3 0 0 0-3 3v2a3 3 0 0 0 3 3h7m-7 0h7a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H8"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16"></path>
                    </svg>
                </button>

                <div class="w-px bg-gray-600 mx-1"></div>

                <button
                    type="button"
                    @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                    :class="{ 'bg-gray-600': editor.isActive('heading', { level: 2 }) }"
                    class="p-2 rounded hover:bg-gray-600 transition text-sm font-bold"
                    title="Heading 2"
                >
                    H2
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                    :class="{ 'bg-gray-600': editor.isActive('heading', { level: 3 }) }"
                    class="p-2 rounded hover:bg-gray-600 transition text-sm font-bold"
                    title="Heading 3"
                >
                    H3
                </button>

                <div class="w-px bg-gray-600 mx-1"></div>

                <button
                    type="button"
                    @click="editor.chain().focus().toggleBulletList().run()"
                    :class="{ 'bg-gray-600': editor.isActive('bulletList') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Bullet List"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().toggleOrderedList().run()"
                    :class="{ 'bg-gray-600': editor.isActive('orderedList') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Numbered List"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 6h13M7 12h13M7 18h13M3 6h.01M3 12h.01M3 18h.01"></path>
                    </svg>
                </button>

                <div class="w-px bg-gray-600 mx-1"></div>

                <button
                    type="button"
                    @click="editor.chain().focus().toggleBlockquote().run()"
                    :class="{ 'bg-gray-600': editor.isActive('blockquote') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Quote"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="setLink"
                    :class="{ 'bg-gray-600': editor.isActive('link') }"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Add Link"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="addImage"
                    class="p-2 rounded hover:bg-gray-600 transition"
                    title="Add Image"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </button>

                <div class="w-px bg-gray-600 mx-1"></div>

                <button
                    type="button"
                    @click="editor.chain().focus().undo().run()"
                    :disabled="!editor.can().undo()"
                    class="p-2 rounded hover:bg-gray-600 transition disabled:opacity-50"
                    title="Undo"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    @click="editor.chain().focus().redo().run()"
                    :disabled="!editor.can().redo()"
                    class="p-2 rounded hover:bg-gray-600 transition disabled:opacity-50"
                    title="Redo"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"></path>
                    </svg>
                </button>
            </div>

            <!-- Editor Content -->
            <editor-content :editor="editor" class="prose prose-invert max-w-none p-4 min-h-[300px] bg-gray-800" />
        </div>

        <!-- Hidden input for form submission -->
        <input type="hidden" :name="name" :value="contentJson" />
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';

const props = defineProps({
    name: {
        type: String,
        default: 'content'
    },
    initialContent: {
        type: [Object, String],
        default: () => ({ type: 'doc', content: [{ type: 'paragraph' }] })
    },
    placeholder: {
        type: String,
        default: 'Write your article content here...'
    }
});

const emit = defineEmits(['update:content']);

const editor = useEditor({
    extensions: [
        StarterKit,
        Image.configure({
            HTMLAttributes: {
                class: 'max-w-full rounded-lg',
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-orange-400 hover:text-orange-300 underline',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    content: typeof props.initialContent === 'string'
        ? JSON.parse(props.initialContent)
        : props.initialContent,
    onUpdate: ({ editor }) => {
        emit('update:content', editor.getJSON());
    },
});

const contentJson = computed(() => {
    if (!editor.value) return '';
    return JSON.stringify(editor.value.getJSON());
});

const setLink = () => {
    const previousUrl = editor.value.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    if (url === null) return;

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const addImage = () => {
    const url = window.prompt('Image URL');

    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
};

// Watch for external content updates
watch(() => props.initialContent, (newContent) => {
    if (editor.value && newContent) {
        const content = typeof newContent === 'string' ? JSON.parse(newContent) : newContent;
        if (JSON.stringify(editor.value.getJSON()) !== JSON.stringify(content)) {
            editor.value.commands.setContent(content);
        }
    }
});

// Listen for external content update events
const handleExternalUpdate = (event) => {
    if (editor.value && event.detail && event.detail.content) {
        editor.value.commands.setContent(event.detail.content);
    }
};

onMounted(() => {
    window.addEventListener('tiptap-set-content', handleExternalUpdate);
});

onBeforeUnmount(() => {
    window.removeEventListener('tiptap-set-content', handleExternalUpdate);
    if (editor.value) {
        editor.value.destroy();
    }
});
</script>

<style>
.tiptap-editor .ProseMirror {
    outline: none;
}

.tiptap-editor .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #6b7280;
    pointer-events: none;
    height: 0;
}

.tiptap-editor .ProseMirror h2 {
    font-size: 1.5rem;
    font-weight: bold;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.tiptap-editor .ProseMirror h3 {
    font-size: 1.25rem;
    font-weight: bold;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
}

.tiptap-editor .ProseMirror p {
    margin-bottom: 0.75rem;
}

.tiptap-editor .ProseMirror ul,
.tiptap-editor .ProseMirror ol {
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
}

.tiptap-editor .ProseMirror ul {
    list-style-type: disc;
}

.tiptap-editor .ProseMirror ol {
    list-style-type: decimal;
}

.tiptap-editor .ProseMirror blockquote {
    border-left: 4px solid #f97316;
    padding-left: 1rem;
    margin-left: 0;
    margin-bottom: 0.75rem;
    color: #9ca3af;
}

.tiptap-editor .ProseMirror img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
    border-radius: 0.5rem;
}
</style>
