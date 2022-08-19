// Bob Jenkins' One-At-A-Time hashing algorithm.
uint hash( uint x ) {
    x += ( x << 10u );
    x ^= ( x >>  6u );
    x += ( x <<  3u );
    x ^= ( x >> 11u );
    x += ( x << 15u );
    return x;
}

vec3 hash_color(uint x) {
    return normalize(vec3(hash(x * uint(3)), hash(x * uint(7)), hash(x * uint(11))));
}