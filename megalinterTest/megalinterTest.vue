<template>
    <p class="introText">{{ message }}</p>
</template>

<script setup>
const message = 'Hello';
</script>

<style>
.introText {
    color: var(--colorText);
}
</style>
