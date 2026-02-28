# tempest-model

This is the Tempest model, a project that can be used to create other projects without having to start from scratch. This enables to me to create projects with my own style and structure, and to reuse code that I have already written.

## Usage

To use the Tempest model, clone the repository and create a new project using the `tempest` command. For example:

```bash
git clone --depth 1 https://github.com/tamdaz/tempest-model my-project
cd my-project
```

> You can name your project whatever you want.

Then, you have to install dependencies:

```bash
composer install
```

And run the server:

```bash
php tempest serve
```

Go to `http://localhost:8000` in your browser.