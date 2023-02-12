/**
 * Scales a matrix by a vector.
 */
mat4 scale_matrix(mat4 matrix, vec3 scale) 
{
    mat4 scaled = mat4(1.0);
    scaled[0][0] = scale.x;
    scaled[1][1] = scale.y;
    scaled[2][2] = scale.z;
    
    return matrix * scaled;
}