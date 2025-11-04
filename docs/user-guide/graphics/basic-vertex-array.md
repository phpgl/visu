---
title: VertexArrays (Basic)
---

VISU's `BasicVertexArray` gives you a clean, high-level way to define and render simple vertex data in OpenGL. It removes the boilerplate of creating VAOs/VBOs and wiring up attributes.

## Introduction

In OpenGL, a **Vertex Array Object (VAO)** records how your vertex attributes are read, while a **Vertex Buffer Object (VBO)** holds the raw vertex data. `BasicVertexArray` packages those pieces into a simple API that:

- lets you declare a float-only layout (for example `[3, 2]` for `vec3` position + `vec2` UV)
- calculates stride and byte offsets for you
- sets up attribute pointers in the correct order
- provides simple bind-and-draw helpers

!!! note

    `BasicVertexArray` is intentionally float-only. If you need integer or byte attributes, normalized values, indexed drawing (element buffers), or instancing, use raw OpenGL functions directly or one of VISU's specialized helpers instead.

## Creating a BasicVertexArray

To create a `BasicVertexArray`, you need an instance of `GLState` and a vertex layout definition. The vertex layout is specified as an array of integers, where each integer represents the number of floats for that attribute.

```php
use VISU\Graphics\BasicVertexArray;

// resolve your global GL state (for example from the container)
$glState = $container->resolveGL();

// create a vertex array with a simple layout
// [3, 2] = vec3 position + vec2 texture coordinates = 5 floats per vertex
$vertexArray = new BasicVertexArray($glState, [3, 2]);
```

### Understanding Vertex Layouts

The vertex layout array defines how your vertex data is structured. Each element in the array represents an attribute, and the value indicates how many floats that attribute contains:

```php
// position only (vec3)
$vertexArray = new BasicVertexArray($glState, [3]);

// position (vec3) + texture coordinates (vec2)
$vertexArray = new BasicVertexArray($glState, [3, 2]);

// position (vec3) + normal (vec3) + color (vec4)
$vertexArray = new BasicVertexArray($glState, [3, 3, 4]);

// position (vec3) + texture coordinates (vec2) + normal (vec3) + color (vec4)
$vertexArray = new BasicVertexArray($glState, [3, 2, 3, 4]);
```

The `BasicVertexArray` automatically calculates the stride (total bytes per vertex) and configures the vertex attribute pointers based on your layout specification.

## Uploading Vertex Data

Once you've created a `BasicVertexArray`, you'll need to upload your vertex data to the GPU. This is done using the `upload()` method with a `FloatBuffer`:

```php
use GL\Buffer\FloatBuffer;

// create a buffer with vertex data
$vertexData = new FloatBuffer([
    // vertex 1: position (x, y, z) + texture coords (u, v)
    -0.5, -0.5, 0.0,  0.0, 0.0,
    // vertex 2
     0.5, -0.5, 0.0,  1.0, 0.0,
    // vertex 3
     0.0,  0.5, 0.0,  0.5, 1.0,
]);

// upload the data to the GPU
$vertexArray->upload($vertexData);
```

The vertex data must match your specified layout. For example, if you defined a layout of `[3, 2]`, each vertex must contain exactly 5 floats (3 for position + 2 for texture coordinates).

## Rendering

The `BasicVertexArray` provides convenient methods for rendering your vertex data:

### Drawing All Vertices

To render all vertices in the buffer:

```php
// bind and draw all vertices as triangles (default)
$vertexArray->drawAll();

// or specify a different render mode
$vertexArray->drawAll(GL_LINES);
$vertexArray->drawAll(GL_POINTS);
```

### Drawing a Range of Vertices

To render a specific range of vertices, useful when you have multiple objects in a single buffer:

```php
// draw vertices starting at offset 10, drawing 6 vertices
$vertexArray->draw(10, 6);

// with a specific render mode
$vertexArray->draw(10, 6, GL_LINE_STRIP);
```

### Binding the Vertex Array

If you need to manually bind the vertex array without drawing:

```php
$vertexArray->bind();
// ... perform custom OpenGL operations
glDrawArrays(GL_TRIANGLES, 0, $vertexCount);
```

## Best Practices

### Use Static Data When Possible

The `upload()` method uses `GL_STATIC_DRAW`, which tells OpenGL the data won't change frequently. This is optimal for most game assets like models and static geometry. If you need to update vertex data every frame, consider using a different approach or managing the buffer manually.

### Minimize State Changes

Binding vertex arrays and buffers has a performance cost. Organize your rendering to minimize how often you switch between different vertex arrays:

```php
// good: group objects using the same vertex array
$vertexArray->bind();
$vertexArray->draw(0, 3);    // object 1
$vertexArray->draw(3, 6);    // object 2
$vertexArray->draw(9, 12);   // object 3

// avoid: unnecessary switching between vertex arrays
$vertexArray1->drawAll();
$vertexArray2->drawAll();
$vertexArray3->drawAll();
```

### Match Layout to Shaders

Ensure your vertex layout matches your shader's input attributes. The layout array maps to OpenGL's location attributes in order:

```php
// vertex layout: [3, 2, 4]
$vertexArray = new BasicVertexArray($glState, [3, 2, 4]);

// corresponding shader attributes
// layout(location = 0) in vec3 a_pos;     // first [3]
// layout(location = 1) in vec2 a_uv;      // second [2]
// layout(location = 2) in vec4 a_color;   // third [4]
```

### Resource Cleanup

The `BasicVertexArray` automatically cleans up OpenGL resources when destroyed. However, be mindful of object lifetimes to avoid holding references longer than needed, and the other way around.

## When Not to Use BasicVertexArray

While `BasicVertexArray` is convenient for many scenarios, you should consider working directly with OpenGL when:

- You need non-float vertex attributes (integers, bytes, etc.)
- You require indexed drawing with element buffers
- You're implementing advanced rendering techniques like instancing (see [BasicInstancedVertexArray](#) for that)

## See Also

- [BasicInstancedVertexArray](#) - For instanced rendering with per-instance attributes
- [Shaders](./shader.md) - learn about shader management in VISU
- [QuadVertexArray](#) - A pre-configured vertex array for rendering screen-space quads
