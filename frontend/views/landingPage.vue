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

    <!-- How RevisionHub Works -->
    <section class="howRevisionWorks">
        <div class="container">
            <h2 class="howRevisionWorksTitle">How Revision Works</h2>

            <div class="howRevisionWorksSteps">
                <div class="howRevisionWorksStep">
                    <div class="howRevisionWorksIcon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="howRevisionWorksContent">
                        <h3>Choose a test</h3>
                        <p>Select a subject and practice test from the available question banks.</p>
                    </div>
                </div>

                <div class="howRevisionWorksStep">
                    <div class="howRevisionWorksIcon">
                        <i class="bi bi-play-circle"></i>
                    </div>
                    <div class="howRevisionWorksContent">
                        <h3>Take the test</h3>
                        <p>Answer multiple-choice questions at your own pace or within a time limit.</p>
                    </div>
                </div>

                <div class="howRevisionWorksStep">
                    <div class="howRevisionWorksIcon">
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <div class="howRevisionWorksContent">
                        <h3>Track your progress</h3>
                        <p>View your results, identify weak areas and monitor your improvement over time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
