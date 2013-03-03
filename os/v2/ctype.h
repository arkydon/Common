#ifndef _CTYPE_H
#define _CTYPE_H

typedef unsigned char byte;
typedef unsigned short int word;
typedef unsigned long int dword;
typedef unsigned long int size_t;

typedef struct {
	word offset_low;
	word selector;
	byte reserved;
	byte type;
	word offset_high;
} __attribute__((__packed__)) id_t;

typedef struct {
	word limit_low;
	word base_low;
	byte base_mid;
	byte type;
	byte limit_high;
	byte base_high;
} __attribute__((__packed__)) gd_t;

typedef struct {
	word limit;
	gd_t * gd;
} __attribute__((__packed__)) gdtr_t;

typedef struct {
	word limit;
	id_t * id;
} __attribute__((__packed__)) idtr_t;

#endif
