---
title: Shaders
---

VISU's **shader system** simplifies the process of loading, linking, and managing shaders in your application. It provides an easy way to set **uniforms**, handle `#include` directives, and manage **shader defines**. With this system, you can focus on creating stunning visual effects without getting bogged down in the details of shader management.

## What is a Shader?

A **shader** in the context of VISU is the same as in OpenGL, it is a programmable code snippet that manipulates graphic rendering by taking control over specific stages within the graphics pipeline. Written in **GLSL** (OpenGL Shading Language), shaders are paramount for visual effects, ranging from simple changes in color to complex simulations like dynamic lighting or procedural texture generation.

## Autoloading Shaders

In a project created with `visu-starter`, the **ShaderCollection** object is set up in the `app/core.ctn` file. This object acts as a manager class to hold all shader objects.

```yml
@shaders: VISU\Graphics\ShaderCollection(@GL, :visu.path.resources.shader)
    - enableVISUIncludes()
    - addVISUShaders()
    - scanShaderDirectory(:visu.path.resources.shader)
```

The configuration above performs the following actions:

1. Enables all VISU includes, allowing you to include GLSL files provided by VISU itself.
2. Adds the default VISU shaders to the collection.
3. Scans your project's `./resources/shader` directory for `*.glsl` files.

All files in the `./resources/shader` directory will be automatically loaded, compiled, and linked using the following line in your `Game.php`:

```php
$container->resolveShaders()->loadAll(function($name, ShaderProgram $shader) {
  Logger::info("(shader) loaded: {$name} -> {$shader->id}");
});
```

### Accessing Shaders

Shaders become available in your **ShaderCollection** by their filename. For example, if you have the following files:

```
resources/
  shader/
    background.vert.glsl
    background.frag.glsl
```

You can access them using the following code:

```php
$shaderCollection = $container->resolveShaders();
$shader = $shaderCollection->get('background');
```

This makes it easy to manage and use shaders in your VISU project.

## Manually Creating Shader Objects

While using the **ShaderCollection** is a convenient way to manage shaders, you can also create shader objects manually. This approach is completely valid if you prefer not to use the shader collection. However, note that you won't have the ability to use `#include` when creating shaders this way.

To create a shader object manually, you'll need an instance of `VISU\Graphics\GLState`, which can usually be resolved from the container using `$container->resolveGL()`.

Here's an example of creating a shader program with a simple vertex and fragment shader:

```php
$shader = new ShaderProgram($glstate);
```

Which will create the shader program. Next, attach a simple vertex shader:

```php
$shader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
#version 330 core

layout (location = 0) in vec3 aPos;
layout (location = 1) in vec2 aTexCoord;

out vec2 TexCoords;

void main()
{
    gl_Position = vec4(aPos, 1.0);
    TexCoords = aTexCoord;
}
GLSL));
```

And also attach a simple fragment shader:

```php
$shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_texture;
void main()
{             
    FragColor = texture(u_texture, TexCoords);
}
GLSL));
```

Finally, link the shader program:

```php
$shader->link();
```

## Using / binding a Shader

To set a shader program as the current one in the OpenGL context, use the `use()` 🙈 method on the shader program object:

```php
$shader->use();
```

!!! note

    - Ensure the shader program is linked before calling `use()`, as it will throw a `ShaderProgramException` if the program is not linked.
    - The `use()` method sets the shader program as the current program only if it's not already in use, avoiding unnecessary calls to `glUseProgram()` and improving performance. However, if you use `glUseProgram()` directly, the VISU shader system won't be aware of the change and won't track the current shader program.