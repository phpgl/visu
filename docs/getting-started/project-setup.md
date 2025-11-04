
Setting up a new VISU PHP Game Project.

!!! question "Is PHP-GLFW already installed?"

    Have you read the installation guide yet? If not, please do so first. VISU requires the following:

    - **PHP 8.1** or higher
    - **[Composer](./installation.md#composer)**
    - **[PHP-GLFW](./installation.md#php-glfw)**

    Follow the **[installation guide](./installation.md)** if you **haven't** installed the extensions yet!

## Creating a new VISU project

### Pick your Setup

VISU can be setup in multiple different ways, for the sake of simplicity we are going to focus on two main methods:

1. **🚀 [VISU Quickstart](#visu-quickstart)** (Recommended for beginners)
    <br>
    The VISU Quickstart creates a **minimal** and **lightweight** VISU application for **rapid prototyping**.


2. **⚡ [Full VISU Starter Project](#full-visu-starter-project)** (Advanced)
    <br>
    The VISU Starter Project creates a **fully featured** VISU application with **basic game structure**, asset management, and **example code**. _(Think like a framework with controllers, routing, views, etc.)_

Once you have all the requirements installed, you can create a new VISU project using Composer's `create-project`.

## VISU Quickstart

_You can find the [VISU Quickstart repository here](https://github.com/phpgl/visu-quickstart)._

<figure markdown style="max-width: 200px;">
  ![VISU Quickstart](../docs-assets/visu/getting-started/visu_quickstart.svg)
</figure>

To create a new VISU Quickstart project, run the following Composer command in your terminal:

```bash
composer create-project phpgl/visu-quickstart -s dev --prefer-dist my-php-game
```

This command will create a new VISU project in the `my-php-game` directory.

Then enter the newly created project in your terminal:

```
cd my-php-game
```

And you're ready to go!

```
php bin/start.php
```

<figure markdown style="max-width: 600px;">
  ![VISU Quickstart Window](../docs-assets/visu/getting-started/quickstart_start.gif)
</figure>

You should see a window open with a black background and red ball that you can move around with "WASD" keys.


### Quickstart Structure 

```
my-php-game/
├── app.ctn        <- App Configuration / Dependency Container
├── bootstrap.php  <- Bootstrap / Initialization File
├── composer.json
├── app/           <- Additional configuration / dependencies
├── bin/           <- Executable scripts
├── src/           <- Your game code
│   └── Application.php
├── var/           <- Writable directory for logs, cache, etc.
└── vendor/        <- Composer dependencies
```

## Full VISU Starter Project

_You can find the [VISU Starter Project repository here](https://github.com/phpgl/visu-starter)._

To create a new VISU Starter project, run the following Composer command in your terminal:

```bash
composer create-project phpgl/visu-starter -s dev --prefer-dist my-php-game
```

This command will create a new VISU project in the `my-php-game` directory.

There's a quick wizard that might ask you for a few basics, like your game's name, to create the initial configuration.

After the installation, open the newly created project in your terminal:

```
cd my-php-game
```

### Running the game

Once all dependencies are installed, you can run the game by executing the `play` command:

```bash
./bin/play
```

A window should open with a black background and a few flying elephpants in it.

<figure markdown>
  ![Image title](../docs-assets/visu/getting-started/project-starter-screenshot.png)
  <figcaption>VISU Starter Project Screen</figcaption>
</figure>


### Controls

The controls of the VISU PHP starter game are as follows:

- **W** - Move up
- **A** - Move left
- **S** - Move down
- **D** - Move right
- **F1** - Toggle debug text and profiler
- **ctrl+c** - Toggles the ingame console
- **ESC** - Exit the game