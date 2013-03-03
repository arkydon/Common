# Makefile
CC = gcc -O0 -fomit-frame-pointer -fno-stack-protector -ffreestanding -Wall
LD = ld --oformat=binary
OBJECTS = kernel.o tty.o
HDD = hdd.bin
all: loader.bin kernel.bin
loader.bin: loader.o
	$(LD) -Ttext=0x7c00 loader.o -o loader.bin
loader.o: loader.s
	$(CC) -c loader.s -o loader.o
kernel.bin: $(OBJECTS)
	$(LD) -Ttext=0x100000 $(OBJECTS) -o kernel.bin
.c.o:
	$(CC) -c $< -o $*.o
install: all
	cat kernel.bin >> loader.bin
	dd bs=1024 if=/dev/zero of=$(HDD) count=10404
	dd conv=notrunc if=loader.bin of=$(HDD)
	rm -f loader.bin
	rm -f kernel.bin
	rm -f *.o
