#ifndef _TTY_H
#define _TTY_H

#define VIDEO_WIDTH 80
#define VIDEO_HEIGHT 25
#define VIDEO_RAM 0xb8000

void clrscr();
void putchar(char);
void puts(char *);

#endif
