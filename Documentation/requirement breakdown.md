# task

🟩 = MUST
🟧 = SHOULD
🟨 = COULD

## What are the core requirements

- login
    - secure 🟩
    - GDPR complient 🟩
    - Single log in for all account types 🟩
    - logo, username, password 🟩

    - additional func
        - brute force mitigation 🟧
        - MFA 🟧
        - additional security 🟧
- landing page
    - info about product 🟩
    - contact info 🟩
    - easy access to login views 🟩
- student reg
    - sign up 🟩
    - student info 🟩
        - student number
        - names
        - password
        - course title
        - email
    - <B>must be confirmed by lecturer</b> 🟩

    - Additional
        - appon confirmation of account recieve email 🟧

- student dashboard
    - test selection page
        - list all test 🟩
        - info needed 🟩
            - name of test
            - course 
            - lecturer who set the test
            - time limit
            - completed by date
        - all tests completed listed 🟩
            - test name 
            - completed date
            - number of questions
            - % correct
            - total points
    - test page
        - points +30 for right -10 for wrong 🟩
        - max 4 answers 🟩
        - name of test 🟩
        - subject name 🟩
        - current question number 🟩
        - number of questions 🟩
        - if time limit 🟩
            - live countdown
            - submit test when countdown ends any questions left shoul be set to 0
    - leaderboards 
        - top 5 people from each course 🟩
        - visually appeling 🟧
            - icons 
            - progress bars 
            - SVG 
    - results
        - number of points user has 🟩
        - avg score 🟩
        - line chart of score overtime 🟧

        - additional
            - export completed test data 🟨
- lecture page
    - fully <b>CRUD</b> 🟩
    - student management
        - add filtering and sorting 🟧
        - disable student acc 🟩
        - table for all students 🟩
            - last log in
            - student numbers 
            - name
            - course
        - pending students 🟩
            - for adding new students
    - test management
        - deletion should require confirmation if any student has already done the test 🟧
        - tables like student management 🟩
        - when creating the tests optional time limits should be added 🟩
        - randomly selected questions from a pool 🟩
        - each set should be unique 🟩

        - additional 
            - priorities questions the student is frequently getting wrong 🟨
- admin page
    - subject management
        - CRU 🟩
            - no deleting subjects
            - historical records
    - lecture management
        - CRUD and disable accounts 🟩
- msc features
    - sign out with conf 🟩
