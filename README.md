![logo_s](https://user-images.githubusercontent.com/956212/192290126-b8c481de-9f22-4a8d-99b5-0ea16300ae70.png)


# VISU - PHP Game Framework

A Modern OpenGL Framework for PHP, ex php-game-framework. VISU aims to be a simple, yet powerful framework for creating 2D and 3D games and applications.
It comes with high level abstractions for common tasks but also allows you to access the underlying OpenGL API directly. Additionally, VISU provides an optional framework structure to quickly bootstrap a new application.

**Visu is built on top of [PHP-GLFW](https://phpgl.net) so make sure the extension is installed and enabled.**

## Features 

* Shader Management with Support for Macros and Includes
* Transition Animation System
* Command Line Interface for Creating Build Tools/Scripts
* An Entity Component System (ECS)
* Low-Poly Rendering Pipeline
* Tons of Helpers and Geometric Abstractions like Bounding Boxes, Raycasting, Transformations, etc.
* A Render Graph-ish Rendering Pipeline for Managing & Creating Complex and Deep Rendering Pipelines.
* Render Resource Management.
* Texture Manager, Loaders, and Helpers.
* Font Rendering
* Basic Heightmap Capturing
* Gizmo Rendering
* SSAO Render Pass
* Basic 3D Debugging Helpers (Drawing Bounding Boxes, Rays, etc.)
* Framebuffer Management and Abstractions.
* Basic Profiling, GPU and CPU Time.
* Fixed Timestep Game Loop
* Input Handling (Mouse & Keyboard) with Key Binding Maps and Different Interaction Maps.
* Event Dispatching.
* Quickstart Collections to Rapidly Get an App Started.
* In-Game Interactive Console.
* Signal Queues.
* Camera System for 2D and 3D.
* And Much More.

## Usage 

Want to build something with VISU?

Check out the docs: visu.phpgl.net/

### Quickstart

https://github.com/phpgl/visu-quickstart

Use Composer to create a new project based on visu-quickstart:

```bash
composer create-project phpgl/visu-quickstart -s dev --prefer-dist my-visu-project 
```

After the installation is complete, you can start the application by running:

```bash
cd my-visu-project
php ./bin/start.php
```

### Scafolding

https://github.com/phpgl/visu-starter


