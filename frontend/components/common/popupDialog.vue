<script>
export default {
    props: {
        title: {
            type: String,
            required: true,
        },
    },
    emits: ['close'],
    methods: {
        open() {
            this.$refs.dialog.showModal();
        },
        close() {
            this.$refs.dialog.close();
        },
    },
};
</script>

<template>
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
        <div class="popupFooter d-flex justify-content-end gap-2">
            <slot name="buttons" />
        </div>
    </dialog>
</template>

<style scoped>
.popupDialog {
    display: none;
    flex-direction: column;
    width: min(650px, calc(100vw - 32px));
    max-width: 650px;
    max-height: calc(100vh - 40px);
    max-height: calc(100dvh - 40px);
    margin: auto;
    padding: 0;
    box-sizing: border-box;
    overflow: hidden;
    border: none;
    border-radius: var(--radiusMd);
    background: var(--colorSurface);
    color: var(--colorText);
}

.popupHeader {
    flex: 0 0 auto;
}

.popupBody {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.popupFooter {
    flex: 0 0 auto;
}

.popupDialog::backdrop {
    background: var(--colorText);
    opacity: 0.72;
    backdrop-filter: blur(4px);
}

.popupDialog[open] {
    display: flex;
}

@media (max-width: 639px) {
    .popupDialog {
        width: calc(100vw - 24px);
        max-height: calc(100dvh - 24px);
    }
}
</style>
