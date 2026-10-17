<template>
  <p class="intro-text">{{ message }}</p>
</template>

<script setup>
var message = "Hello";
if (message == "Hello") {
  console.log(message);
}
</script>

<style>
.intro-text {
  color: #ff0000;
}
</style>
