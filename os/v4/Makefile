CC = gcc -O0 -fno-stack-protector -fomit-frame-pointer -Wall -ffreestanding
LD = ld
OBJECTS = $(shell find . -iname '*.c' -or -iname '*.s' | sed 's/[c|s]$$/o/g')
HDD = hdd.bin
KERNEL = kernel.elf
HDDMOUNT = /mnt/vfs
C = 600
H = 8
S = 30
CHS = $(shell echo $(C) \* $(H) \* $(S) | bc)
SIZE_OF_PART = $(shell echo $(CHS) - $(S) | bc)
BLOCKS = $(shell echo $(SIZE_OF_PART) \/ 2 | bc)
OFFSET = $(shell echo $(S) \* 512 | bc)
OFFSET_IN_BLOCKS = $(shell echo $(OFFSET) \/ 1024 | bc)
GRUB_PATH = /usr/lib/grub/i386-pc
TMP = /tmp/tmp.file
###
all: install
proto:
	for i in *.c; do sed -i '/Proto/d' $$i; done
	###
	for i in *.c; do cproto $$i; done > $(TMP).0
	sed -i 's|$$| // Proto|' $(TMP).0
	for i in *.c ; do \
		cat $$i | head -n 2 > $(TMP).1 ; \
		cat $(TMP).0 >> $(TMP).1 ; \
		cat $$i | tail -n +3 >> $(TMP).1 ; \
		mv $(TMP).1 $$i ; \
	done
	###
	for i in *.c; do touch -am -r . $$i; done
unproto:
	for i in *.c; do sed -i '/Proto/d' $$i; done
kernel: $(OBJECTS)
	$(LD) -Ttext=0x100000 $(OBJECTS) -o $(KERNEL)
.c.o:
	$(CC) -c $< -o $*.o
.s.o:
	$(CC) -c $< -o $*.o
install: clean proto kernel unproto
	umount $(HDDMOUNT) > /dev/null 2>&1 || true
	dd bs=1024 if=$(HDD) of=$(TMP) skip=$(OFFSET_IN_BLOCKS)
	mount -o loop $(TMP) $(HDDMOUNT)
	cp $(KERNEL) $(HDDMOUNT)
	rm -f $(KERNEL)
	echo test > $(HDDMOUNT)/test
	umount $(HDDMOUNT) > /dev/null 2>&1 || true
	dd bs=1024 conv=notrunc if=$(TMP) of=$(HDD) seek=$(OFFSET_IN_BLOCKS)
	rm -f $(TMP)
	find . -iname '*.o' -exec rm -f {} \;
clean:
	umount $(HDDMOUNT) > /dev/null 2>&1 || true
	find . -iname '*.o' -exec rm -f {} \;
prepare:
	umount $(HDDMOUNT) > /dev/null 2>&1 || true
	rm -f $(TMP)
	mkdosfs -I -S 512 -v -C $(TMP) $(BLOCKS)
	mount -o loop $(TMP) $(HDDMOUNT)
	mkdir -p $(HDDMOUNT)/boot/grub
	cp $(GRUB_PATH)/* $(HDDMOUNT)/boot/grub
	echo "default 0 \n title El Nino \n root (hd0,0) \n kernel /$(KERNEL)" > $(HDDMOUNT)/boot/grub/menu.lst
	umount $(HDDMOUNT) > /dev/null 2>&1 || true
	dd if=/dev/zero of=$(HDD) bs=512 count=$(CHS)
	echo "$(S),$(SIZE_OF_PART),b,*" | sfdisk -C$(C) -H$(H) -S$(S) -uS -f -L --no-reread $(HDD) > /dev/null 2>&1
	dd bs=512 conv=notrunc if=$(TMP) of=$(HDD) seek=$(S)
	rm -f $(TMP)
	echo "device (hd0) $(HDD) \n root (hd0,0) \n setup (hd0) \n quit" | grub --device-map=/dev/null --batch
trash:
	###losetup -d /dev/loop0
	###dd bs=512 if=$(HDD) of=$(TMP) skip=$(S)
	##losetup -o $(OFFSET) /dev/loop0 $(HDD)
	### mkdosfs -I -s 16 -S 512 -v -C asd 71985
	#  $(SRCS:.c=.o)
	## -static
	### export TEMP = `cat $(TMP).0`
	### echo $$TEMP | grep -c .
	### echo $TEMP
	### 
	### for i in *.c; do cat $(TMP).0 $$i > $(TMP).1 && mv $(TMP).1 $$i; done
	###
	### for i in *.c; do sed -i '/#include/d' $$i; done
	###
	### for i in *.h; do echo \#include \"$$i\"; done > $(TMP).0
	### for i in *.c; do cat $(TMP).0 $$i > $(TMP).1 && mv $(TMP).1 $$i; done
	###
	#
	###/dev/loop0 > /dev/null 2>&1 || true
	#losetup -d /dev/loop1 > /dev/null 2>&1 || true
	##losetup -d /dev/loop0 > /dev/null 2>&1 || true
	#parted $(HDD) mklabel msdos
	#parted --script $(HDD) mkpartf primary fat16 $(OFFSET)b 64
	#parted $(HDD) set 1 boot on
	#### > $(TMP)
	#losetup /dev/loop0 $(HDD)
	#losetup -o $(OFFSET) /dev/loop0 $(HDD)
	##mkdosfs -I -c -s 1 -S 512 -v /dev/loop0 143970
	##mount /dev/loop0 $(HDDMOUNT)
	#umount /dev/loop0 > /dev/null 2>&1 || true
	#losetup -d /dev/loop1
	##losetup -d /dev/loop0 > /dev/null 2>&1 || true