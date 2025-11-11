---
hide:
  - navigation
---

# **VISU** - A Modern **Game** Framework for **PHP**

VISU is a component based php game engine. _Think Laravel or Symfony, but for game development._

This framework is built around the extension **[PHP-GLFW](https://github.com/mario-deluna/php-glfw)** which is requried for VISU to work.

## Core Philosophy

### Replace what you want, keep what you need

VISU is built around the idea of modularity. You can replace any system you want with your own implementation, while keeping the rest of the framework intact.

- Most parts of VISU are decoupled by design and don't require the entire framework stack to work. If you want to use our rendering helpers go ahead.
- There is no fixed rendering pipeline, you can freely extend or replace the existing rendering systems with your own.




<div class="grid cards" markdown>

-   :material-clock-fast:{ .lg .middle } __Install in 5 minutes__

    ---

    If you haven't installed PHP-GLFW yet, follow the installation guide for your platform.

    ---
    [:octicons-arrow-right-24: **Installation**](./getting-started/installation.md)

-   :material-lightbulb:{ .lg .middle } __PHP GameDev Tutorial__

    ---

    Everything ready to get started? Jump right into the tutorial on writing games with PHP.

    ---
    [:octicons-arrow-right-24: **Getting Started**](./getting-started/project-setup.md)

-   :material-lightbulb:{ .lg .middle } __PHP OpenGL Tutorial__

    ---

    ![PHP-GLFW](./../docs-assets/php-glfw/getting_started/basic_pipeline.png){ width="100%"}

    Want to first understand the core? Checkout the tutorial on writing OpenGL applications with PHP.

    This is not a VISU tutorial, but it will help you understand the core concepts of OpenGL, which 
    will help you develop better games with VISU.
    ---

    [:octicons-arrow-right-24: **Getting Started** (PHP-GLFW)](https://phpgl.net/getting-started/window-creation.html)

-   :material-play-circle:{ .lg .middle } __Examples & Games__

    ---

    ![PHP-GLFW](./../docs-assets/visu/games/php-towerdefense/screenshot_01.jpg){ width="100%"}

    There is a small collection of examples and games available to get you started.

    ---

    [:octicons-arrow-right-24: **Games**](./games/0-php-games.md)
</div>
