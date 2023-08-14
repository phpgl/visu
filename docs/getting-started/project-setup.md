
Setting up a new VISU PHP Game Project.

!!! question "Is PHP-GLFW already installed?"

    Have you read the installation guide yet? If not, please do so first. VISU requires the following:

    - **PHP 8.1** or higher
    - **[Composer](./installation.md#composer)**
    - **[PHP-GLFW](./installation.md#php-glfw)**

    Follow the **[installation guide](./installation.md)** if you **haven't** installed the extensions yet!

## Creating a new VISU project

Once you have all the requirements installed, you can create a new VISU project using Composer's `create-project` command:

```bash
composer create-project phpgl/visu-starter -s dev --prefer-dist my-php-game
```

This command will create a new VISU project in the `my-php-game` directory.

There's a quick wizard that might ask you for a few basics, like your game's name, to create the initial configuration.

After the installation, open the newly created project in your terminal:

```
cd my-php-game
```

## Running the game

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