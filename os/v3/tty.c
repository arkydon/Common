#include "ctype.h"
#include "tty.h"

dword tty_cursor = 0; //положение курсора
dword tty_attribute = 7; //текущий аттрибут символа

void clrscr() {
	dword i;
	char * video = (char *)VIDEO_RAM;
	for (i = 0; i < VIDEO_HEIGHT * VIDEO_WIDTH; i++)
		*(video + i * 2) = ' ';
	tty_cursor = 0;
}

void putchar(char c) {
	char * video = (char *)VIDEO_RAM;
	dword i;
	switch(c) {
	case '\n': //Если это символ новой строки
		tty_cursor += VIDEO_WIDTH;
		tty_cursor -= tty_cursor % VIDEO_WIDTH;
		break;
	case 8: //backspace
		tty_cursor--;
		*(video + tty_cursor * 2) = ' ';
    		break;
	default:
		*(video + tty_cursor * 2) = c;
		*(video + tty_cursor * 2 + 1) = tty_attribute;
		tty_cursor++;
		break;
	}
	//Если курсор вышел за границу экрана, сдвинем экран вверх на одну строку
	if(tty_cursor > VIDEO_WIDTH * VIDEO_HEIGHT) {
		for(i = VIDEO_WIDTH * 2; i <= VIDEO_WIDTH * VIDEO_HEIGHT * 2 + VIDEO_WIDTH * 2; i++)
			*(video + i - VIDEO_WIDTH * 2) =* (video + i);
		tty_cursor -= VIDEO_WIDTH;
	}
}

void puts(char * s) {
	while(*s) {
		putchar(*s);
		s++;
	}
}
