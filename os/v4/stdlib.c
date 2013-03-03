#include "ctype.h"
#include "stdlib.h"
// GO
dword tty_cursor = 0; // Положение курсора
dword tty_attribute = 7; // Текущий аттрибут символа
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
	case '\n': // Если это символ новой строки
		tty_cursor += VIDEO_WIDTH;
		tty_cursor -= tty_cursor % VIDEO_WIDTH;
		break;
	case 8: // Backspace
		tty_cursor--;
		*(video + tty_cursor * 2) = ' ';
    		break;
	default:
		*(video + tty_cursor * 2) = c;
		*(video + tty_cursor * 2 + 1) = tty_attribute;
		tty_cursor++;
		break;
	}
	// Если курсор вышел за границу экрана, сдвинем экран вверх на одну строку
	if(tty_cursor > VIDEO_WIDTH * VIDEO_HEIGHT) {
		for(i = VIDEO_WIDTH * 2; i <= VIDEO_WIDTH * VIDEO_HEIGHT * 2 + VIDEO_WIDTH * 2; i++)
			*(video + i - VIDEO_WIDTH * 2) = *(video + i);
		tty_cursor -= VIDEO_WIDTH;
	}
}
void puts(char * s) {
	while(*s) {
		putchar(*s);
		s++;
	}
}
char * strncpy(char * dest, char * source, size_t n) {
	char * start = dest;
	while(n && (*dest++ = *source++)) n--;
	if(n) while(--n) *dest++ = '\0';
	return start;
}
void * memcpy(void * dst, void * src, size_t n) {
	void * ret = dst;
	while(n--) {
		*(char *)dst = *(char *)src;
		dst = (char *)dst + 1;
		src = (char *)src + 1;
	}
	return ret;
}
char * strchr(char * s, int ch) {
	while(*s && *s != (char) ch) s++;
	if(*s == (char)ch) return (char *)s;
	return NULL;
}
int strcasecmp(const char * s1, char * s2) {
	char f, l;
	do {
		f = ((*s1 <= 'Z') && (*s1 >= 'A')) ? *s1 + 'a' - 'A' : *s1;
		l = ((*s2 <= 'Z') && (*s2 >= 'A')) ? *s2 + 'a' - 'A' : *s2;
		s1++;
		s2++;
	} while ((f) && (f == l));
	return (int)(f - l);
}
/*
 putchar is the only external dependency for this file,
 if you have a working putchar, just remove the following
 define. If the function should be called something else,
 replace outbyte(c) by your own function call.
*/
/* #define putchar(c) outbyte(c) */
static void printchar(char **str, int c)
{
if (str) {
**str = c;
++(*str);
}
else (void)putchar(c);
}
#define PAD_RIGHT 1
#define PAD_ZERO 2
static int prints(char **out, const char *string, int width, int pad)
{
register int pc = 0, padchar = ' ';
if (width > 0) {
register int len = 0;
register const char *ptr;
for (ptr = string; *ptr; ++ptr) ++len;
if (len >= width) width = 0;
else width -= len;
if (pad & PAD_ZERO) padchar = '0';
}
if (!(pad & PAD_RIGHT)) {
for ( ; width > 0; --width) {
printchar (out, padchar);
++pc;
}
}
for ( ; *string ; ++string) {
printchar (out, *string);
++pc;
}
for ( ; width > 0; --width) {
printchar (out, padchar);
++pc;
}
return pc;
}
/* the following should be enough for 32 bit int */
#define PRINT_BUF_LEN 12
static int printi(char **out, int i, int b, int sg, int width, int pad, int letbase)
{
char print_buf[PRINT_BUF_LEN];
register char *s;
register int t, neg = 0, pc = 0;
register unsigned int u = i;
if (i == 0) {
print_buf[0] = '0';
print_buf[1] = '\0';
return prints (out, print_buf, width, pad);
}
if (sg && b == 10 && i < 0) {
neg = 1;
u = -i;
}
s = print_buf + PRINT_BUF_LEN-1;
*s = '\0';
while (u) {
t = u % b;
if( t >= 10 )
t += letbase - '0' - 10;
*--s = t + '0';
u /= b;
}
if (neg) {
if( width && (pad & PAD_ZERO) ) {
printchar (out, '-');
++pc;
--width;
}
else {
*--s = '-';
}
}
return pc + prints (out, s, width, pad);
}
static int print(char **out, int *varg)
{
register int width, pad;
register int pc = 0;
register char *format = (char *)(*varg++);
char scr[2];
for (; *format != 0; ++format) {
if (*format == '%') {
++format;
width = pad = 0;
if (*format == '\0') break;
if (*format == '%') goto out;
if (*format == '-') {
++format;
pad = PAD_RIGHT;
}
while (*format == '0') {
++format;
pad |= PAD_ZERO;
}
for ( ; *format >= '0' && *format <= '9'; ++format) {
width *= 10;
width += *format - '0';
}
if( *format == 's' ) {
register char *s = *((char **)varg++);
pc += prints (out, s?s:"(null)", width, pad);
continue;
}
if( *format == 'd' ) {
pc += printi (out, *varg++, 10, 1, width, pad, 'a');
continue;
}
if( *format == 'x' ) {
pc += printi (out, *varg++, 16, 0, width, pad, 'a');
continue;
}
if( *format == 'X' ) {
pc += printi (out, *varg++, 16, 0, width, pad, 'A');
continue;
}
if( *format == 'u' ) {
pc += printi (out, *varg++, 10, 0, width, pad, 'a');
continue;
}
if( *format == 'c' ) {
/* char are converted to int then pushed on the stack */
scr[0] = *varg++;
scr[1] = '\0';
pc += prints (out, scr, width, pad);
continue;
}
}
else {
out:
printchar (out, *format);
++pc;
}
}
if (out) **out = '\0';
return pc;
}
/* assuming sizeof(void *) == sizeof(int) */
int printf(const char *format, ...)
{
register int *varg = (int *)(&format);
return print(0, varg);
}
int sprintf(char *out, const char *format, ...)
{
register int *varg = (int *)(&format);
return print(&out, varg);
}



