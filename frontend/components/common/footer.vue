<template>
    <footer class="siteFooter">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between">
                <p class="footerCopyright mb-0">&copy; 2026 RevisionHub Systems Inc. All rights reserved.</p>

                <div class="d-flex gap-3">
                    <router-link to="/privacy-policy" class="footerPrivacyLink"> Privacy Policy </router-link>
                    <router-link to="/terms" class="footerPrivacyLink"> Terms and Conditions </router-link>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
/* moved from main.css, only this component uses these styles */

/* Shared footer */
.siteFooter {
    width: 100%;
    margin-top: auto;
    padding: var(--spaceMd) var(--spaceXl);
    border-top: 1px solid var(--colorPrimary);
    background-color: var(--colorBrand);
    color: var(--colorSurface);
    font-size: var(--fontSizeSmall);
}

.footerCopyright,
.footerPrivacyLink {
    color: var(--colorSurface);
}

.footerPrivacyLink {
    background-color: var(--colorBrand);
    border-top: 1px solid var(--colorPrimary);
    color: var(--colorSurface);
    font-size: var(--fontSizeFooter);
    padding: var(--spaceFooterBlock);
    text-decoration: none;
}

@media (min-width: 768px) {
    .siteFooter {
        padding: var(--spaceFooterBlock) var(--spaceFooterInline);
    }
}

.footerPrivacyLink:hover {
    text-decoration: underline;
}

@media (max-width: 767.98px) {
    .siteFooter {
        padding: var(--spaceFooterMobile);
    }
}
</style>
