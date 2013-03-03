CC = gcc -O0 -fno-stack-protector -fomit-frame-pointer -ffreestanding -Wall
LD = ld
OBJECTS = kernel.o mboot.o tty.o
HDD = hdd.bin
KERNEL = kernel.elf
HDDMOUNT = /mnt/vfs
C = 600
H = 8
S = 30
CHS = $(shell echo $(C) \* $(H) \* $(S) | bc)
SIZE_OF_PART = $(shell echo $(CHS) - $(S) | bc)
OFFSET = $(shell echo $(S) \* 512 | bc)
LIST = /tmp/list.tmp
GRUB_PATH = /usr/lib/grub/i386-pc
#
#
#
all: kernel
kernel: $(OBJECTS)
	$(LD) -Ttext=0x100000 $(OBJECTS) -o $(KERNEL)
.c.o:
	$(CC) -c $< -o $*.o
.s.o:
	$(CC) -c $< -o $*.o
install: all
	losetup -o $(OFFSET) /dev/loop0 $(HDD)
	mount /dev/loop0 $(HDDMOUNT)
	mv $(KERNEL) $(HDDMOUNT)
	umount /dev/loop0
	losetup -d /dev/loop0
	rm -f *.o
prepare:
	dd if=/dev/zero of=$(HDD) bs=512 count=$(CHS)
	echo "$(S),$(SIZE_OF_PART),b,*" > $(LIST)
	cat $(LIST) | sfdisk -C$(C) -H$(H) -S$(S) -uS -f -L --no-reread $(HDD)
	losetup /dev/loop0 $(HDD)
	losetup -o $(OFFSET) /dev/loop1 /dev/loop0
	mkdosfs -v -F 32 /dev/loop1
	mount /dev/loop1 $(HDDMOUNT)
	mkdir -p $(HDDMOUNT)/boot/grub
	cp $(GRUB_PATH)/* $(HDDMOUNT)/boot/grub
	echo "default 0" > $(LIST)
	echo "title El Nino" >> $(LIST)
	echo "root (hd0,0)" >> $(LIST)
	echo "kernel /$(KERNEL)" >> $(LIST)
	mv $(LIST) $(HDDMOUNT)/boot/grub/menu.lst
	umount /dev/loop1
	losetup -d /dev/loop1
	losetup -d /dev/loop0
	echo "device (hd0) $(HDD) \n root (hd0,0) \n setup (hd0) \n quit" | grub --device-map=/dev/null --batch
