#ifndef GAMMA_CORR_GLSL
#define GAMMA_CORR_GLSL
/**
 * Gamma Correction
 * ----------------------------------------------------------------------------
 */

// gamma constant definition
uniform float u_display_gamma = 2.2;

/**
 * Apply gamma correction to a color
 */
vec3 gamma_correct(vec3 color)
{
    return pow(color, vec3(1.0 / u_display_gamma));
}

#endif