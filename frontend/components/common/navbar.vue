<template>
    <a class="skipLink" href="#main-content">
        <!-- Allows keyboard users to skip navigation and reach the main content -->
        Skip to main content
    </a>

    <header class="siteHeader">
        <!-- Shared header used across the application -->
        <nav class="navbar navbar-expand" aria-label="Main navigation">
            <!-- Bootstrap navbar keeps the header layout consistent -->
            <div class="container-fluid">
                <a class="navbar-brand" href="/" aria-label="RevisionHub home">
                    <img src="../../assets/images/Logo.png" alt="RevisionHub home" class="siteLogo" />
                </a>

                <!-- Align account actions to the right -->
                <div class="ms-auto d-flex align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <a href="/login" class="headerButton loginButton"> Sign in </a>

                        <!-- Opens the page where students create an account. -->
                        <a href="/register" class="headerButton registerButton"> Register </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>
</template>
