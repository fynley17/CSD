<script>
// shared pop-up (house style section 5), uses the browser's own <dialog> tag
// showModal() keeps the keyboard inside it and Esc closes it, so there's no extra code for that
// whichever page uses it puts its own message and buttons in, e.g. <PopupDialog title="Account locked">
export default {
    props: {
        // goes top left, and it's what a screen reader says when it opens
        title: {
            type: String,
            required: true,
        },
    },
    emits: ['close'],
    methods: {
        /**
         * Opens it on top of the page, everything behind goes dark
         */
        open() {
            this.$refs.dialog.showModal();
        },

        /**
         * Closes it, used by the X and any button the page adds
         */
        close() {
            this.$refs.dialog.close();
        },
    },
};
</script>

<template>
    <!-- close fires however it gets shut (X, Esc or a button), so the page only has to listen for that -->
    <dialog ref="dialog" class="popupDialog" :aria-label="title" @close="$emit('close')">
        <div class="popupHeader d-flex justify-content-between align-items-center">
            <h2 class="popupTitle mb-0">{{ title }}</h2>
            <button type="button" class="popupCloseButton" aria-label="Close" @click="close">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="popupBody">
            <slot />
        </div>
        <div class="popupFooter d-flex gap-2">
            <slot name="buttons" />
        </div>
    </dialog>
</template>
