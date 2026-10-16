// This file is the rule book for checking the Vue and JavaScript code in the csd website (CTS-155).
// Every rule comes from the RevisionHub house style. On each pull request, MegaLinter reads this
// file and highlights lines of code breaking a rule, before the code is merged into main project.
// file name ends in .cjs, since that format is readable by MegaLinter and other computers.

// "path" is a helper built into JavaScript that joins folder names together correctly 
const path = require('path');

/** 
  finds, then loads one of the checking tools these rules need, such as the Vue checker
  looks in the frontend folder first, since that's where the tools are kept on 
  if a tool is not there, it uses the copy that MegaLinter installs for itself when it runs on GitHub.

  @param {string} name - ... the npm package name, i.e. 'eslint-plugin-vue'
  @returns {object} - the loaded package ready for usage
 */   
function load(name) {
  return require(require.resolve(name, {paths: [path.join(__dirname, 'frontend'), __dirname] }));
}

// checking for common javascript mistakes
const js = load('@eslint/js');
// list of names a web browser already provides like "window" and "document"
const globals = load('globals'); 
// checks specially for vue files
const pluginVue = load('eslint-plugin-vue'); 
// Checks that pages can be used by everyone, such as people using screen readers
const pluginVueA11y = load('eslint-plugin-vuejs-accessibility');

// a list since each item adds more rules, and later items can change earlier ones
module.exports = [
    { 
        // never checked since they're downloaded tools or built files
        ignores: ['**/node_modules/**', '**/vendor/**', 'frontend/dist/**'],
    },

    // uses each tool's recommended checks
    js.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    ...pluginVueA11y.configs['flat/recommended'],

    // RevisionHub House style rules, which apply to .js and .vue files inside the frontend folder
    {
        files: ['frontend/**/*.{js,vue}'],
        languageOptions: {
            // newest version JavaScript used
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser},
        },
        rules: {
          // section 8 requires camelcase for everything
          camelcase: ['error', { properties: 'always' }],
          'vue/attribute-hyphenation': ['error', 'never'],
          'vue/v-on-event-hyphenation': ['error', 'never', { autofix: false}],
          'vue/prop-name-casing': ['error', 'camelCase'],
          'vue/custom-event-name-casing': ['error', 'camelCase'],

          // section 8 - no line can be longer than 120 characters, so code is easy to read 
          // without scrolling sideways. A tab is 4 spaces. 
          'vue/max-len': ['error', { code: 120, template: 120, comments: 120, tabWidth: 4 }],
          
          // section 8 requires Vue components be at least 2 words (loginForm.vue, not login.vue)
          // to prevent clashing with HTML's one word tags, like <button>
          'vue/multi-word-component-names': 'error',
  
          // section 8 JS Rules
          // store values with "const", or "let" if the value will change. "Var" is not allowed
          'no-var': 'error',
          'prefer-const': 'error',

          // compare 2 values with === only, the shorter == can treat different values as equal
          eqeqeq: ['error', 'always'],

          // removes anything left over, like unused names or console.log messages
          'no-unused-vars': 'error',
          'no-console': 'error', 
  
          // section 8 requires no eval or innerHTML with user input, so text is never run as if it were code
          // raw text is never put straight into the page, this is to prevent harmful code being put into
          // a form box, which the website would run
          'no-eval': 'error',
          'no-implied-eval': 'error',
          'no-new-func': 'error',
          'vue/no-v-html': 'error',
          'no-restricted-syntax': [
            'error',
            {
                selector: 'AssignmentExpression[left.property.name="innerHTML"]',
                message: 'No innerHTML (house style section 8). Use textContent or a Vue template.',
            },
          ],
  
          // section 6 requires labels on every input, wrapped or linked with for/id
          // allows screen readers to say out loud what the box is for
          'vuejs-accessibility/label-has-for': ['error', {required: {some: ['nesting', 'id'] } }],
      },        
  },
      // vite.config.js runs on the pc that builds the website, not in the browser
      // computer names like 'process' can be used instead of browser names 
  {  
      files: ['frontend/vite.config.js'],
      languageOptions: { globals: { ...globals.node} },
  },
];

  






          
