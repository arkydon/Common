.set MBOOT_HEADER_MAGIC, 0x1badb002
.set MBOOT_PAGE_ALIGN, 0x1
.set MBOOT_MEMORY_INFO, 0x2
.set MBOOT_VIDEO_MODE, 0x4
.set MBOOT_AOUT_KLUDGE, 0x10000
.set MBOOT_FLAGS, MBOOT_MEMORY_INFO

.set STACK_SIZE, 0x8000

.extern kernel

.text
.code32
.globl _start
_start:
jmp _real_start

.align 4
_mboot_header:
.long MBOOT_HEADER_MAGIC
.long MBOOT_FLAGS
.long -(MBOOT_FLAGS + MBOOT_HEADER_MAGIC)

_real_start:
mov $(_stack + STACK_SIZE), %esp
push $0x0
popf
push %ebx
push %eax
call kernel
jmp .
/* Our stack */
.comm _stack, STACK_SIZE
