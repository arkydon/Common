#ifndef _KERNEL_H
#define _KERNEL_H

#define KERNEL_CS_SELECTOR 0x8
#define KERNEL_DS_SELECTOR 0x10

#define EXC_HANDLER(function) \
void function(); \
asm( \
	"\n .globl " #function \
	"\n " #function ":" \
	"\n pusha" \
	"\n call _" #function \
	"\n popa" \
	"\n add $0x4, %esp" \
	"\n iret" \
); \
void _ ## function()

#endif
