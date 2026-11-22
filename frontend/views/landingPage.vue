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
                        src="../assets/images/heroImage.jpg"
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
            <h2 class="howRevisionWorksTitle">How RevisionHub Works</h2>

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
    <!-- Why use RevisionHub -->
    <section class="whyRevisionHub">
        <div class="container">
            <h2 class="whyRevisionHubTitle">Why use RevisionHub?</h2>

            <div class="whyRevisionHubCards">
                <div class="whyRevisionHubCard">
                    <i class="bi bi-journal-bookmark whyRevisionHubIcon"></i>
                    <h3>Subject-Based Tests</h3>
                    <p>Access practice tests and revision materials organised by subject.</p>
                </div>

                <div class="whyRevisionHubCard">
                    <i class="bi bi-graph-up-arrow whyRevisionHubIcon"></i>
                    <h3>Progress Tracking</h3>
                    <p>Monitor your scores, identify areas for improvement over time.</p>
                </div>

                <div class="whyRevisionHubCard">
                    <i class="bi bi-trophy whyRevisionHubIcon"></i>
                    <h3>Leaderboards</h3>
                    <p>Compare your performance with other students and see how you rank.</p>
                </div>

                <div class="whyRevisionHubCard">
                    <i class="bi bi-bullseye whyRevisionHubIcon"></i>
                    <h3>Personalised Revision</h3>
                    <p>Focus on the topics you find most challenging with targeted practice and feedback.</p>
                </div>
            </div>
        </div>
    </section>
    <!-- Ready to start revising -->
    <section class="revisionCallToAction">
        <div class="container">
            <h2 class="revisionCallToActionTitle">Ready to start revising?</h2>

            <p class="revisionCallToActionText">
                Create your RevisionHub account and gain access to practice tests, revision materials and more.
            </p>

            <router-link to="/register" class="headerButton loginButton"> Create an account </router-link>
        </div>
    </section>
    <!-- Contact RevisionHub -->
    <section class="contactRevisionHub">
        <div class="container">
            <h2 class="contactRevisionHubTitle">Contact RevisionHub</h2>

            <div class="contactRevisionHubDetails">
                <div class="contactRevisionHubItem">
                    <div class="contactRevisionHubIcon">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div>
                        <h3>Email</h3>
                        <a href="mailto:revisionhub.help@gmail.com">revisionhub.help@gmail.com</a>
                    </div>
                </div>

                <div class="contactRevisionHubItem">
                    <div class="contactRevisionHubIcon">
                        <i class="bi bi-telephone"></i>
                    </div>
                    <div>
                        <h3>Phone</h3>
                        <a href="tel:01225123456">01225 123456</a>
                    </div>
                </div>

                <div class="contactRevisionHubItem">
                    <div class="contactRevisionHubIcon">
                        <i class="bi bi-person-vcard"></i>
                    </div>
                    <div>
                        <h3>Address</h3>
                        <p>
                            RevisionHub Systems Inc.<br />
                            Weston Super Mare, United Kingdom
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* moved from main.css, only this component uses these styles */

/* Landing page: shared section headings and content width */
.landingHeroContainer,
.howRevisionWorks .container,
.whyRevisionHub .container,
.revisionCallToAction .container,
.contactRevisionHub .container {
    width: 100%;
    max-width: var(--contentMaxWidthLarge);
    margin-right: auto;
    margin-left: auto;
    padding-right: var(--contentPadding);
    padding-left: var(--contentPadding);
}

.howRevisionWorksTitle,
.whyRevisionHubTitle,
.revisionCallToActionTitle,
.contactRevisionHubTitle {
    margin: 0 0 var(--spaceLg);
    color: var(--colorBrand);
    text-align: center;
    font-size: var(--fontSizeH1);
    font-weight: 700;
}

/* Hero */
.landingHero {
    display: flex;
    width: 100%;
    min-height: 500px;
    align-items: center;
    padding: var(--spaceHeroVertical) 0;
    background-color: var(--colorHeroBackground);
}

.landingHeroContainer .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: var(--space2xl);
    width: 100%;
    margin: 0;
}

.landingHeroImage {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
}

.landingHeroContainer .row > .landingHeroContent,
.landingHeroContainer .row > .landingHeroImage {
    width: 100%;
    max-width: none;
    min-width: 0;
    padding: 0;
}

.landingHeroTitle {
    max-width: 750px;
    margin: 0 0 var(--spaceLg);
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeHeroTitle);
    line-height: 1.1;
    overflow-wrap: normal;
}

.landingHeroText {
    max-width: 600px;
    margin-bottom: var(--spaceHeroTextBottom);
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeBody);
    line-height: 1.5;
}

.landingHeroActions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spaceMd);
}

/* stylelint-disable-next-line no-descending-specificity */
.landingHeroActions .headerButton {
    min-width: 155px;
    min-height: 48px;
    padding: var(--spaceButtonVertical) var(--spaceButtonHorizontal);
}

.landingHeroIllustration {
    display: block;
    width: 100%;
    max-width: 700px;
    height: auto;
    object-fit: contain;
    mix-blend-mode: multiply;
}

/* How RevisionHub Works */
.howRevisionWorks {
    padding: var(--spaceSectionTop) 0 var(--spaceSectionBottom);
    background-color: var(--colorHowRevisionWorksBackground);
}

.howRevisionWorksSteps {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: center;
    gap: var(--spaceXl);
}

.howRevisionWorksStep {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: var(--spaceSm);
}

.howRevisionWorksIcon {
    display: flex;
    flex: 0 0 84px;
    width: 84px;
    height: 84px;
    align-items: center;
    justify-content: center;
    background-color: transparent;
    color: var(--colorPrimary);
    font-size: var(--fontSizeH1);
}

.howRevisionWorksContent h3 {
    margin: 0 0 var(--spaceHeadingBottom);
    color: var(--colorBrand);
    font-size: var(--fontSizeH3);
    font-weight: 600;
}

.howRevisionWorksContent p {
    margin: 0;
    font-size: var(--fontSizeBody);
    line-height: 1.2;
}

/* Why use RevisionHub? */
.whyRevisionHub {
    padding: var(--spaceSectionWhyTop) 0 var(--spaceSectionWhyBottom);
    background-color: var(--colorWhyRevisionHubBackground);
}

.whyRevisionHubCards {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: var(--spaceLg);
}

.whyRevisionHubCard {
    padding: var(--spaceWhyCardVertical) var(--spaceWhyCardHorizontal);
    border-radius: var(--radiusMd);
    background-color: var(--colorSurface);
}

.whyRevisionHubIcon {
    display: block;
    margin-bottom: var(--spaceIconBottom);
    color: var(--colorPrimary);
    font-size: var(--fontSizeH1);
}

.whyRevisionHubCard h3 {
    margin-bottom: var(--spaceTiny);
    color: var(--colorBrand);
    font-size: var(--fontSizeH3);
    font-weight: 600;
}

.whyRevisionHubCard p {
    margin: 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeBody);
    line-height: 1.5;
}

/* Registration call to action */
.revisionCallToAction {
    padding: var(--spaceSectionCallToAction) 0;
    background-color: var(--colorSurface);
    text-align: center;
}

.revisionCallToActionText {
    max-width: 700px;
    margin: 0 auto var(--spaceCallToActionTextBottom);
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeBody);
    line-height: 1.5;
}

/* stylelint-disable-next-line no-descending-specificity */
.revisionCallToAction .headerButton {
    min-height: 48px;
    padding: var(--spaceButtonVertical) var(--spaceButtonWideHorizontal);
}

/* Contact RevisionHub */
.contactRevisionHub {
    padding: var(--spaceSectionContact) 0;
    background-color: var(--colorContactRevisionHubBackground);
}

.contactRevisionHubDetails {
    display: grid;
    width: 100%;
    grid-template-columns: 1fr 1fr 1fr;
}

.contactRevisionHubItem {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spaceContactGap);
}

.contactRevisionHubIcon {
    display: flex;
    flex: 0 0 56px;
    width: 56px;
    height: 56px;
    align-items: center;
    justify-content: center;
    background-color: transparent;
    color: var(--colorPrimary);
    font-size: var(--fontSizeH1);
}

.contactRevisionHubItem h3 {
    margin: 0 0 var(--spaceContactHeading);
    color: var(--colorBrand);
    font-size: var(--fontSizeH3);
    font-weight: 600;
}

/* stylelint-disable-next-line no-descending-specificity */
.contactRevisionHubItem a,
.contactRevisionHubItem p {
    margin: 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeContactText);
    line-height: 1.5;
    text-decoration: none;
    overflow-wrap: anywhere;
}

.contactRevisionHubItem a:hover {
    text-decoration: underline;
}

/* Responsive layouts */
@media (max-width: 991.98px) {
    .whyRevisionHubCards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--spaceGridTablet);
    }
}

@media (max-width: 767.98px) {
    .landingHero {
        min-height: auto;
        padding: var(--spaceHeroMobile) 0;
    }

    .landingHeroContainer .row {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--spaceGridMobile);
    }

    .landingHeroTitle {
        margin-bottom: var(--spaceHeroMobileTitle);
        font-size: var(--fontSizeHeroTitleMobile);
    }

    .landingHeroText {
        font-size: var(--fontSizeBody);
    }

    .landingHeroImage {
        display: none;
    }

    .landingHeroActions {
        gap: var(--spaceMobileActions);
    }

    .landingHeroActions .headerButton {
        min-width: 125px;
    }

    .landingHeroIllustration {
        max-width: 500px;
    }

    .howRevisionWorks {
        padding: var(--spaceMobileSection) 0;
    }

    .howRevisionWorksTitle,
    .whyRevisionHubTitle,
    .revisionCallToActionTitle,
    .contactRevisionHubTitle {
        font-size: var(--fontSizeH2);
    }

    .howRevisionWorksTitle {
        margin-bottom: var(--spaceHeroMobileTitle);
    }

    .howRevisionWorksSteps,
    .contactRevisionHubDetails {
        grid-template-columns: 1fr;
        gap: var(--spaceMobileSection);
    }

    .whyRevisionHub {
        padding: var(--spaceMobileSection) 0;
    }

    .revisionCallToAction {
        padding: var(--spaceMobileCallToAction) 0;
    }

    .revisionCallToActionText {
        font-size: var(--fontSizeBody);
    }

    .contactRevisionHubTitle {
        margin-bottom: var(--spaceContactTitleBottom);
    }

    .contactRevisionHubItem {
        justify-content: flex-start;
    }
}

@media (max-width: 575.98px) {
    .whyRevisionHubCards {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 400px) {
    .landingHeroActions .headerButton {
        min-width: 0;
        flex: 1;
        padding: var(--spaceMobileButton) var(--spaceMobileButtonHorizontal);
    }
}
</style>
