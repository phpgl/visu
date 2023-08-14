!!! question "Is PHP-GLFW already installed?"

    ### If you've already installed `PHP-GLFW`, the [**Project Setup Guide**](./project-setup.md#creating-a-new-visu-project) is what you're looking for.

To install and use VISU, you'll need a few things:

- [**PHP 8.1 or higher**](#php)
- [**Composer**](#composer)
- [**PHP-GLFW**](#php-glfw)

In this guide, we'll quickly go over each of these requirements. Feel free to skip any of these steps if you already have them installed.

## PHP

VISU requires **PHP 8.1** or a higher version. If you haven't installed PHP yet:

- Download it from the [official PHP website](https://www.php.net/downloads.php).
- Alternatively, you can use your favorite package manager (`brew`, `apt`, etc.) to get it.

Do keep in mind the version requirement. VISU won't function with older PHP editions. To check your PHP version, execute `php -v` in your terminal.

### Can I use Docker? No

Considering the dynamic world of software development, many prefer containerization using platforms like Docker. However, there's a hiccup: **PHP-GLFW can't** operate in a Docker container. The culprit? PHP-GLFW's need for an active display server. So, for now, native installations are the way to go.

## Composer

VISU uses [Composer](https://getcomposer.org/) to manage its dependencies. If you don't have Composer installed yet, you can download it from the [official Composer website](https://getcomposer.org/download/).

## PHP-GLFW

VISU is built entirely around [PHP-GLFW](https://github.com/mario-deluna/php-glfw) and requires it to be installed. PHP-GLFW is a PHP extension that provides bindings for GLFW and OpenGL, allowing you to create and manage windows, OpenGL contexts, and handle input events, etc.

PHP-GLFW is supported on Linux, macOS, and Windows. You can find installation instructions for your operating system below:

- [**Linux** Installation](https://phpgl.net/getting-started/installation/install-linux.html)
- [**macOS** Installation](https://phpgl.net/getting-started/installation/install-macos.html)
- [**Windows** Installation](https://phpgl.net/getting-started/installation/install-windows.html)


## Installing VISU

As VISU is a framework for PHP its installation is done via Composer. To create a new VISU project follow the next setps:

[Project Setup →](project-setup.md#creating-a-new-visu-project){ .md-button }