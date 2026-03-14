#define MINIMP3_IMPLEMENTATION
#define MINIMP3_NO_STDIO
#include "minimp3.h"
#include <stdlib.h>
#include <string.h>

typedef struct {
    short *pcm;
    int samples;      // total samples (per channel)
    int channels;
    int sample_rate;
    int error;
} mp3_result_t;

/**
 * Decode an MP3 file from a memory buffer into interleaved 16-bit PCM.
 * Caller must free result->pcm via mp3_free().
 */
void mp3_decode_buffer(const unsigned char *data, int data_size, mp3_result_t *result) {
    mp3dec_t dec;
    mp3dec_frame_info_t info;
    short pcm_frame[MINIMP3_MAX_SAMPLES_PER_FRAME];

    mp3dec_init(&dec);

    result->pcm = NULL;
    result->samples = 0;
    result->channels = 0;
    result->sample_rate = 0;
    result->error = 0;

    int total_samples = 0;
    int capacity = 0;
    short *output = NULL;
    int offset = 0;

    while (offset < data_size) {
        int samples = mp3dec_decode_frame(&dec, data + offset, data_size - offset, pcm_frame, &info);

        if (info.frame_bytes == 0) {
            break; // no more frames
        }

        offset += info.frame_bytes;

        if (samples <= 0) {
            continue;
        }

        if (result->channels == 0) {
            result->channels = info.channels;
            result->sample_rate = info.hz;
        }

        int new_samples = samples * info.channels;
        if (total_samples + new_samples > capacity) {
            capacity = (capacity == 0) ? 65536 : capacity * 2;
            while (capacity < total_samples + new_samples) {
                capacity *= 2;
            }
            short *tmp = (short *)realloc(output, capacity * sizeof(short));
            if (!tmp) {
                free(output);
                result->error = 1;
                return;
            }
            output = tmp;
        }

        memcpy(output + total_samples, pcm_frame, new_samples * sizeof(short));
        total_samples += new_samples;
    }

    result->pcm = output;
    result->samples = result->channels > 0 ? total_samples / result->channels : 0;
}

/**
 * Free PCM data allocated by mp3_decode_buffer.
 */
void mp3_free(void *ptr) {
    free(ptr);
}