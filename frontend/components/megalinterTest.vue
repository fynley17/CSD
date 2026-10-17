<template>
    <form>
        <label for="studentNumber">Student number</label>
        <input id="studentNumber" type="text" />
        <img src="/logo.png" alt="RevisionHub home" />
        <p>{{ exampleNumber }}</p>
    </form>
</template>

<script setup>
const exampleNumber = 'S123456';
</script>