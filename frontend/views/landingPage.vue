<template>
    <section class="landingHero">
        <div class="container-fluid landingHeroContainer">
            <div class="row">
                <!-- Left side: main heading and buttons -->
                <div class="col-12 col-lg-5 landingHeroContent">
                    <h1 class="landingHeroTitle">Practice Smarter, Track your progress, Achieve more.</h1>

                    <p class="landingHeroText">
                        Access subject-based practice tests, monitor your performance and identify areas for improvement
                        with RevisionHub.
                    </p>

                    <div class="landingHeroActions">
                        <router-link to="/register" class="headerButton loginButton"> Get started </router-link>

                        <router-link to="/login" class="headerButton registerButton"> Log in </router-link>
                    </div>
                </div>

                <!-- Right side: revision illustration -->
                <div class="col-12 col-lg-5 landingHeroImage">
                    <img
                        src="../assets/images/Hero-Image.jpg"
                        alt="Student using RevisionHub to practise"
                        class="landingHeroIllustration"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
