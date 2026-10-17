<template>
  <div class="container py-5">
    <!-- Page heading and the date the policy was last revised -->
    <h1>Privacy Policy</h1>
    <p class="text-muted">Last updated: 16 October 2026</p>

    <!-- Introduces RevisionHub and explains the purpose of this policy -->
    <p>
      RevisionHub is a reinforcement testing platform developed as part of the
      Collaborative Software Development university project. The platform is
      designed to help students practise subject material, complete tests and
      monitor their learning progress.
    </p>

    <!-- Explains who is developing the application -->
    <p>
      RevisionHub is being developed and maintained by a team of three students.
      As part of the project, the team aims to handle personal information
      appropriately and implement suitable security measures to protect users'
      information.
    </p>

    <!-- Section 1: Identifies the types of personal information
         that the application may need to process -->
    <section class="mb-4">
      <h2>1. Information We Collect</h2>

      <p>
        RevisionHub is designed to process information needed to provide its
        services. Depending on the features used, this information may include:
      </p>

      <ul>
        <li>
          <strong>Account information:</strong> Names, usernames, email
          addresses, account types and account status.
        </li>
        <li>
          <strong>Student information:</strong> Student identifiers, course or
          cohort membership and other details required to manage student
          accounts.
        </li>
        <li>
          <strong>Learning activity:</strong> Tests attempted, submitted
          answers, completion dates, scores, points and progress information.
        </li>
        <li>
          <strong>Account security information:</strong> Failed login attempts,
          account lockout information and last login times, where these features
          are implemented.
        </li>
      </ul>

      <!-- Passwords should be hashed rather than stored as plain text -->
      <p>
        Passwords will be securely hashed before being stored in the database,
        rather than being stored as plain text. The information collected will
        depend on the functionality implemented in the platform.
      </p>
    </section>

    <!-- Section 2: Explains why the collected information is needed -->
    <section class="mb-4">
      <h2>2. How We Use Your Information</h2>

      <p>
        Personal information is intended to be used for the following purposes:
      </p>

      <ul>
        <li>Create and manage user accounts.</li>
        <li>Authenticate users and manage access to the platform.</li>
        <li>Provide revision tests, questions and answer submissions.</li>
        <li>Calculate scores, points and learning progress.</li>
        <li>Display subject leaderboards showing student names and points.</li>
        <li>
          Allow lecturers to manage tests, questions and student progress.
        </li>
        <li>
          Allow administrators to manage lecturers, subjects, student accounts
          and tests.
        </li>
        <li>Maintain the security and operation of the platform.</li>
      </ul>

      <p>
        Personal information should only be used for purposes relevant to
        providing and managing RevisionHub, subject to the applicable data
        protection requirements.
      </p>
    </section>

    <!-- Section 3: Describes the different permissions intended
         for students, lecturers and administrators -->
    <section class="mb-4">
      <h2>3. Access to Information</h2>

      <p>
        RevisionHub is designed to provide different levels of access according
        to user roles:
      </p>

      <ul>
        <li>
          <strong>Students:</strong> Access their own learning information,
          complete tests, view their results and see subject leaderboards.
        </li>
        <li>
          <strong>Lecturers:</strong> Manage tests and questions, manage student
          accounts, and monitor student progress.
        </li>
        <li>
          <strong>Administrators:</strong> Manage lecturers, subjects, student
          accounts and tests.
        </li>
      </ul>

      <!-- Role-based permissions must be implemented and tested
           in the backend; describing them here does not implement them -->
      <p>
        Access permissions must be implemented and tested to help ensure that
        users can only access information appropriate to their role.
      </p>
    </section>

    <!-- Section 4: Makes users aware that leaderboard information
         may be visible to other platform users -->
    <section class="mb-4">
      <h2>4. Leaderboards</h2>

      <p>
        RevisionHub is designed to display a leaderboard showing the top
        students for each subject. Student names and points will be visible to
        other users of the platform.
      </p>

      <p>
        Students should be aware that leaderboard information may be visible to
        other users and is not limited to their own individual results.
      </p>
    </section>

    <!-- Section 5: Describes the technologies used to store
         and manage application data -->
    <section class="mb-4">
      <h2>5. Data Storage and Hosting</h2>

      <p>
        RevisionHub uses a MySQL database to store information required by the
        platform. phpMyAdmin is used to manage and inspect the database. The
        application is developed using Vue.js and PHP and uses Plesk as part of
        its hosting environment.
      </p>
    </section>

    <!-- Section 6: Documents the intended security measures -->
    <section class="mb-4">
      <h2>6. Data Security</h2>

      <p>
        RevisionHub is being developed with the aim of protecting personal
        information against unauthorised access, loss, alteration and
        disclosure.
      </p>

      <p>Planned security measures include:</p>

      <ul>
        <li>Secure password hashing.</li>
        <li>Prepared statements or parameterised database queries.</li>
        <li>Validation of user input.</li>
        <li>Secure session management.</li>
        <li>
          Role-based access controls for students, lecturers and administrators.
        </li>
        <li>Account lockout following three failed login attempts.</li>
        <li>Multi-factor authentication (MFA).</li>
        <li>
          Keeping database credentials and other secrets out of version control.
        </li>
      </ul>

      <!-- Make clear that planned security controls must be
           implemented and tested before they can be relied upon -->
      <p>
        These measures must be implemented and tested before the platform goes
        live. Users should not assume that planned security features are
        operational until they have been verified.
      </p>
    </section>

    <!-- Section 7: Explains how long information should be retained -->
    <section class="mb-4">
      <h2>7. Data Retention and Deletion</h2>

      <p>
        Personal information should only be retained for as long as it is needed
        for the purposes for which it was collected, subject to any applicable
        legal, regulatory or academic record-keeping requirements.
      </p>

      <p>
        Information should be securely deleted or anonymised when it is no
        longer required and there is no valid reason to retain it.
      </p>
    </section>

    <!-- Section 8: Outlines data protection legislation and
         explains that individual rights depend on the circumstances -->
    <section class="mb-4">
      <h2>8. Data Protection and Your Rights</h2>

      <p>
        RevisionHub is intended to handle personal information in accordance
        with applicable data protection legislation, including the UK General
        Data Protection Regulation (UK GDPR) and the Data Protection Act 2018.
      </p>

      <p>
        Depending on the circumstances, individuals may have rights to access
        their personal information, correct inaccurate information, request
        deletion, restrict processing, object to certain processing and request
        the transfer of their information.
      </p>

      <p>
        These rights are subject to applicable legal conditions and exceptions.
        Requests should be directed to the organisation or contact responsible
        for handling data protection enquiries about RevisionHub.
      </p>
    </section>

    <!-- Section 9: Explains that the policy should be reviewed
         when the application's practices change -->
    <section class="mb-4">
      <h2>9. Changes to This Policy</h2>

      <p>
        This Privacy Policy may be updated as RevisionHub continues to be
        developed and new features are introduced.
      </p>

      <p>
        The date at the top of this page will be updated whenever the policy is
        revised.
      </p>
    </section>

    <!-- Section 10: Provides a contact route for privacy questions.
         Replace the placeholder with your university email address. -->
    <section class="mb-4">
      <h2>10. Contact Us</h2>

      <p>
        If you have questions about this Privacy Policy or how your personal
        information is handled, please contact the RevisionHub project team
        using the designated university email address.
      </p>

      <p>Contact email: WS382593@weston.ac.uk</p>
    </section>
  </div>
</template>
