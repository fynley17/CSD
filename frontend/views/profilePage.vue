<script>
import { changePassword, getProfile, updateProfile } from '../services/profileService';

// the same rules PHP checks, so most mistakes are caught before anything is sent
const NAME_PATTERN = /^[a-zA-Z\s'-]{2,50}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PASSWORD_RULES = 'Use at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol';
const NAME_RULES = 'between 2 and 50 characters, using only letters, spaces, hyphens or apostrophes';

// what each account type is called on the page, and what its username is called
const ACCOUNT_TYPES = {
    student: { label: 'Student', usernameLabel: 'Student number' },
    lecturer: { label: 'Lecturer', usernameLabel: 'Staff ID' },
    admin: { label: 'Administrator', usernameLabel: 'Staff ID' },
};

function meetsPasswordRules(password) {
    return (
        password.length >= 8 &&
        /[A-Z]/.test(password) &&
        /[a-z]/.test(password) &&
        /\d/.test(password) &&
        /[^A-Za-z0-9\s]/.test(password)
    );
}

// My profile page, any signed in user can see their account and change their own names, email and password
// PHP only ever works on whoever is logged in, so the page never sends a user ID
export default {
    data() {
        return {
            isLoading: true,
            loadError: '',
            profile: null,
            details: { firstName: '', lastName: '', email: '' },
            detailErrors: { firstName: '', lastName: '', email: '' },
            detailsServerError: '',
            detailsSuccess: '',
            isSavingDetails: false,
            passwords: { currentPassword: '', newPassword: '', confirmPassword: '' },
            passwordErrors: { currentPassword: '', newPassword: '', confirmPassword: '' },
            passwordServerError: '',
            passwordSuccess: '',
            isSavingPassword: false,
            isPasswordShown: false,
        };
    },
    computed: {
        accountType() {
            return ACCOUNT_TYPES[this.profile?.accountType] || { label: '', usernameLabel: 'Username' };
        },
        hasDetailErrors() {
            return Object.values(this.detailErrors).some((error) => error !== '');
        },
        hasPasswordErrors() {
            return Object.values(this.passwordErrors).some((error) => error !== '');
        },
        passwordRules() {
            return PASSWORD_RULES;
        },
    },
    async mounted() {
        try {
            this.showProfile(await getProfile());
        } catch (error) {
            this.loadError = error.response?.data?.error || 'Something went wrong. Try again later';
        } finally {
            this.isLoading = false;
        }
    },
    methods: {
        // fills the read only details and the form with what PHP has saved
        showProfile(profile) {
            this.profile = profile;
            this.details = { firstName: profile.firstName, lastName: profile.lastName, email: profile.email };
        },

        async saveDetails() {
            this.detailsServerError = '';
            this.detailsSuccess = '';

            if (this.checkDetails()) {
                this.isSavingDetails = true;
                try {
                    const result = await updateProfile(
                        this.details.firstName,
                        this.details.lastName,
                        this.details.email,
                    );
                    this.showProfile(result.profile);
                    this.detailsSuccess = result.message;
                } catch (error) {
                    this.detailsServerError = error.response?.data?.error || 'Something went wrong. Try again later';
                    // the names can still have saved even though the email couldn't be sent
                    if (error.response?.data?.profile) {
                        this.showProfile(error.response.data.profile);
                    }
                } finally {
                    this.isSavingDetails = false;
                }
            }

            // moving focus to the message means a screen reader reads it out straight away
            await this.$nextTick();
            this.$refs.detailsMessage?.focus();
        },

        checkDetails() {
            const firstName = this.details.firstName.trim();
            const lastName = this.details.lastName.trim();
            const email = this.details.email.trim();

            this.detailErrors.firstName = NAME_PATTERN.test(firstName) ? '' : `Enter a first name ${NAME_RULES}`;
            this.detailErrors.lastName = NAME_PATTERN.test(lastName) ? '' : `Enter a last name ${NAME_RULES}`;

            if (email === '') {
                this.detailErrors.email = 'Enter your email address';
            } else if (!EMAIL_PATTERN.test(email)) {
                this.detailErrors.email = 'Enter an email address in the correct format, like name@example.com';
            } else {
                this.detailErrors.email = '';
            }

            return !this.hasDetailErrors;
        },

        async savePassword() {
            this.passwordServerError = '';
            this.passwordSuccess = '';

            if (this.checkPasswords()) {
                this.isSavingPassword = true;
                try {
                    const result = await changePassword(
                        this.passwords.currentPassword,
                        this.passwords.newPassword,
                        this.passwords.confirmPassword,
                    );
                    this.passwordSuccess = result.message;
                    // nothing left in the boxes for the next person at this computer
                    this.passwords = { currentPassword: '', newPassword: '', confirmPassword: '' };
                    this.isPasswordShown = false;
                } catch (error) {
                    this.passwordServerError = error.response?.data?.error || 'Something went wrong. Try again later';
                } finally {
                    this.isSavingPassword = false;
                }
            }

            await this.$nextTick();
            this.$refs.passwordMessage?.focus();
        },

        checkPasswords() {
            const { currentPassword, newPassword, confirmPassword } = this.passwords;

            this.passwordErrors.currentPassword = currentPassword === '' ? 'Enter your current password' : '';

            if (newPassword === '') {
                this.passwordErrors.newPassword = 'Enter a new password';
            } else if (!meetsPasswordRules(newPassword)) {
                this.passwordErrors.newPassword = PASSWORD_RULES;
            } else {
                this.passwordErrors.newPassword = '';
            }

            if (confirmPassword === '') {
                this.passwordErrors.confirmPassword = 'Confirm your new password';
            } else if (confirmPassword !== newPassword) {
                this.passwordErrors.confirmPassword = 'Your new passwords do not match';
            } else {
                this.passwordErrors.confirmPassword = '';
            }

            return !this.hasPasswordErrors;
        },

        // the error and hint ids a box should point screen readers at
        describedBy(...ids) {
            const present = ids.filter(Boolean);
            return present.length ? present.join(' ') : undefined;
        },
    },
};
</script>

<template>
    <div class="container py-5 profilePage">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-7">
                <h1 class="mb-4">My profile</h1>

                <p v-if="isLoading" class="secondaryText" role="status">Loading your profile…</p>

                <div v-else-if="loadError" class="loginForm">
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <p class="mb-0"><strong>There is a problem:</strong> {{ loadError }}</p>
                    </div>
                </div>

                <template v-else>
                    <!-- the details only a lecturer or administrator can change -->
                    <section class="card surfaceCard loginForm mb-4" aria-labelledby="accountHeading">
                        <div class="card-body p-4">
                            <h2 id="accountHeading" class="h4 mb-3">Account</h2>
                            <dl class="accountDetails mb-2">
                                <div>
                                    <dt>{{ accountType.usernameLabel }}</dt>
                                    <dd>{{ profile.username }}</dd>
                                </div>
                                <div>
                                    <dt>Account type</dt>
                                    <dd>{{ accountType.label }}</dd>
                                </div>
                                <div v-if="profile.accountType === 'student'">
                                    <dt>Cohort</dt>
                                    <dd>{{ profile.cohortName || 'Not in a cohort' }}</dd>
                                </div>
                            </dl>
                            <p class="secondaryText mb-0">
                                To change these, contact your lecturer or an administrator.
                            </p>
                        </div>
                    </section>

                    <section class="card surfaceCard loginForm mb-4" aria-labelledby="detailsHeading">
                        <div class="card-body p-4">
                            <h2 id="detailsHeading" class="h4 mb-3">Your details</h2>

                            <div
                                v-if="detailsSuccess"
                                ref="detailsMessage"
                                class="alert successMessage d-flex gap-2"
                                role="status"
                                tabindex="-1"
                            >
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="8 12 11 15 16 9" />
                                </svg>
                                <p class="mb-0"><strong>Success:</strong> {{ detailsSuccess }}</p>
                            </div>

                            <div
                                v-if="hasDetailErrors || detailsServerError"
                                ref="detailsMessage"
                                class="alert alert-danger d-flex gap-2"
                                role="alert"
                                tabindex="-1"
                            >
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <div v-if="hasDetailErrors">
                                    <h3 class="messageTitle">There is a problem</h3>
                                    <ul class="messageList mb-0">
                                        <li v-if="detailErrors.firstName">
                                            <a href="#profileFirstName">{{ detailErrors.firstName }}</a>
                                        </li>
                                        <li v-if="detailErrors.lastName">
                                            <a href="#profileLastName">{{ detailErrors.lastName }}</a>
                                        </li>
                                        <li v-if="detailErrors.email">
                                            <a href="#profileEmail">{{ detailErrors.email }}</a>
                                        </li>
                                    </ul>
                                </div>
                                <p v-else class="mb-0"><strong>There is a problem:</strong> {{ detailsServerError }}</p>
                            </div>

                            <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
                            <form novalidate @submit.prevent="saveDetails">
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="profileFirstName" class="form-label">First name</label>
                                        <p v-if="detailErrors.firstName" id="profileFirstNameError" class="fieldError">
                                            Error: {{ detailErrors.firstName }}
                                        </p>
                                        <input
                                            id="profileFirstName"
                                            v-model="details.firstName"
                                            type="text"
                                            class="form-control"
                                            :class="{ inputError: detailErrors.firstName }"
                                            autocomplete="given-name"
                                            :aria-invalid="detailErrors.firstName ? 'true' : 'false'"
                                            :aria-describedby="
                                                describedBy(detailErrors.firstName && 'profileFirstNameError')
                                            "
                                        />
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label for="profileLastName" class="form-label">Last name</label>
                                        <p v-if="detailErrors.lastName" id="profileLastNameError" class="fieldError">
                                            Error: {{ detailErrors.lastName }}
                                        </p>
                                        <input
                                            id="profileLastName"
                                            v-model="details.lastName"
                                            type="text"
                                            class="form-control"
                                            :class="{ inputError: detailErrors.lastName }"
                                            autocomplete="family-name"
                                            :aria-invalid="detailErrors.lastName ? 'true' : 'false'"
                                            :aria-describedby="
                                                describedBy(detailErrors.lastName && 'profileLastNameError')
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="profileEmail" class="form-label">Email address</label>
                                    <p id="profileEmailHint" class="form-text">
                                        If you change this, we will send a link to the new address. We keep using your
                                        current address until you open it.
                                    </p>
                                    <p v-if="detailErrors.email" id="profileEmailError" class="fieldError">
                                        Error: {{ detailErrors.email }}
                                    </p>
                                    <input
                                        id="profileEmail"
                                        v-model="details.email"
                                        type="email"
                                        class="form-control"
                                        :class="{ inputError: detailErrors.email }"
                                        autocomplete="email"
                                        :aria-invalid="detailErrors.email ? 'true' : 'false'"
                                        :aria-describedby="
                                            describedBy(
                                                'profileEmailHint',
                                                profile.pendingEmail && 'profilePendingEmail',
                                                detailErrors.email && 'profileEmailError',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="profile.pendingEmail"
                                        id="profilePendingEmail"
                                        class="pendingEmail mt-2 mb-0"
                                    >
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        Waiting for you to confirm
                                        <strong class="verificationEmail">{{ profile.pendingEmail }}</strong>
                                        using the link we emailed to it.
                                    </p>
                                </div>

                                <button type="submit" class="headerButton loginButton" :disabled="isSavingDetails">
                                    {{ isSavingDetails ? 'Saving...' : 'Save details' }}
                                </button>
                            </form>
                        </div>
                    </section>

                    <section class="card surfaceCard loginForm" aria-labelledby="passwordHeading">
                        <div class="card-body p-4">
                            <h2 id="passwordHeading" class="h4 mb-3">Change password</h2>

                            <div
                                v-if="passwordSuccess"
                                ref="passwordMessage"
                                class="alert successMessage d-flex gap-2"
                                role="status"
                                tabindex="-1"
                            >
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="8 12 11 15 16 9" />
                                </svg>
                                <p class="mb-0"><strong>Success:</strong> {{ passwordSuccess }}</p>
                            </div>

                            <div
                                v-if="hasPasswordErrors || passwordServerError"
                                ref="passwordMessage"
                                class="alert alert-danger d-flex gap-2"
                                role="alert"
                                tabindex="-1"
                            >
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <div v-if="hasPasswordErrors">
                                    <h3 class="messageTitle">There is a problem</h3>
                                    <ul class="messageList mb-0">
                                        <li v-if="passwordErrors.currentPassword">
                                            <a href="#currentPassword">{{ passwordErrors.currentPassword }}</a>
                                        </li>
                                        <li v-if="passwordErrors.newPassword">
                                            <a href="#newPassword">{{ passwordErrors.newPassword }}</a>
                                        </li>
                                        <li v-if="passwordErrors.confirmPassword">
                                            <a href="#confirmNewPassword">{{ passwordErrors.confirmPassword }}</a>
                                        </li>
                                    </ul>
                                </div>
                                <p v-else class="mb-0">
                                    <strong>There is a problem:</strong> {{ passwordServerError }}
                                </p>
                            </div>

                            <form novalidate @submit.prevent="savePassword">
                                <div class="mb-3">
                                    <label for="currentPassword" class="form-label">Current password</label>
                                    <p
                                        v-if="passwordErrors.currentPassword"
                                        id="currentPasswordError"
                                        class="fieldError"
                                    >
                                        Error: {{ passwordErrors.currentPassword }}
                                    </p>
                                    <input
                                        id="currentPassword"
                                        v-model="passwords.currentPassword"
                                        :type="isPasswordShown ? 'text' : 'password'"
                                        class="form-control"
                                        :class="{ inputError: passwordErrors.currentPassword }"
                                        autocomplete="current-password"
                                        :aria-invalid="passwordErrors.currentPassword ? 'true' : 'false'"
                                        :aria-describedby="
                                            describedBy(passwordErrors.currentPassword && 'currentPasswordError')
                                        "
                                    />
                                </div>

                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">New password</label>
                                    <p id="newPasswordHint" class="form-text">{{ passwordRules }}</p>
                                    <p v-if="passwordErrors.newPassword" id="newPasswordError" class="fieldError">
                                        Error: {{ passwordErrors.newPassword }}
                                    </p>
                                    <input
                                        id="newPassword"
                                        v-model="passwords.newPassword"
                                        :type="isPasswordShown ? 'text' : 'password'"
                                        class="form-control"
                                        :class="{ inputError: passwordErrors.newPassword }"
                                        autocomplete="new-password"
                                        :aria-invalid="passwordErrors.newPassword ? 'true' : 'false'"
                                        :aria-describedby="
                                            describedBy(
                                                'newPasswordHint',
                                                passwordErrors.newPassword && 'newPasswordError',
                                            )
                                        "
                                    />
                                </div>

                                <div class="mb-3">
                                    <label for="confirmNewPassword" class="form-label">Confirm new password</label>
                                    <p
                                        v-if="passwordErrors.confirmPassword"
                                        id="confirmNewPasswordError"
                                        class="fieldError"
                                    >
                                        Error: {{ passwordErrors.confirmPassword }}
                                    </p>
                                    <input
                                        id="confirmNewPassword"
                                        v-model="passwords.confirmPassword"
                                        :type="isPasswordShown ? 'text' : 'password'"
                                        class="form-control"
                                        :class="{ inputError: passwordErrors.confirmPassword }"
                                        autocomplete="new-password"
                                        :aria-invalid="passwordErrors.confirmPassword ? 'true' : 'false'"
                                        :aria-describedby="
                                            describedBy(passwordErrors.confirmPassword && 'confirmNewPasswordError')
                                        "
                                    />
                                </div>

                                <div class="form-check mb-4">
                                    <input
                                        id="showPasswords"
                                        v-model="isPasswordShown"
                                        class="form-check-input"
                                        type="checkbox"
                                        aria-controls="currentPassword newPassword confirmNewPassword"
                                    />
                                    <label class="form-check-label" for="showPasswords">Show passwords</label>
                                </div>

                                <button type="submit" class="headerButton loginButton" :disabled="isSavingPassword">
                                    {{ isSavingPassword ? 'Changing...' : 'Change password' }}
                                </button>
                            </form>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* label on the left, value on the right, each pair on its own line */
.accountDetails > div {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spaceXs) var(--spaceMd);
    padding: var(--spaceXs) 0;
    border-bottom: 1px solid var(--colorDivider);
}

.accountDetails dt {
    min-width: 140px;
    color: var(--colorText);
    font-weight: 600;
}

.accountDetails dd {
    margin: 0;
    color: var(--colorText);
}

/* the new address waiting for its link, Royal Blue so it reads as information not an error */
.pendingEmail {
    color: var(--colorBrand);
    font-size: var(--fontSizeSmall);
}

/* long email addresses wrap instead of pushing the card wider than a phone screen */
.verificationEmail {
    overflow-wrap: anywhere;
}
</style>
