#include "ctype.h"
#include "kernel.h"
#include "tty.h"
// GDT
gd_t gd[256];
gdtr_t gdtr;
// IDT
id_t id[256];
idtr_t idtr;
// Kernel
void kernel(dword magic, dword addr) {
	// Init GDT
	set_gd(gd + 0, 0x0, 0x0, 0x0); // zero
	set_gd(gd + 1, 0x0, 0xcfffff, 0x9a); // cs
	set_gd(gd + 2, 0x0, 0xcfffff, 0x92); // ds
	gdtr.limit = 0x2000;
	gdtr.gd = gd;
	asm("lgdt %0"::"m"(gdtr));
	// Init IRQ
	init_irq();
	// Init IDT
	populate_idt(id);
	idtr.limit = 0x7ff;
	idtr.id = id;
	asm("lidt %0"::"m"(idtr));
	// Permit int
	asm("sti");
	// Print something
	clrscr();
	puts("Starting...\n");
	// Loop
	for(;;);
}
// Setup global descriptor
void set_gd(gd_t * gd, dword addr, dword limit, byte type) {
	gd -> limit_low = (word)(limit & 0xffff);
	gd -> limit_high = (byte)(limit >> 16);
	gd -> base_low = (word)(addr & 0xffff);
	gd -> base_mid = (byte)((addr >> 16) & 0xff);
	gd -> base_high = (byte)(addr >> 24);
	gd -> type = type;
}
// Setup interrupt descriptor
void set_id(id_t * id, dword addr, word selector, byte type) {
	id -> offset_high = (word)(addr >> 16);
	id -> offset_low = (word)(addr & 0xffff);
	id -> type = type;
	id -> selector = selector;
	id -> reserved = 0;
}
//
EXC_HANDLER(exc_reserved) {
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_divide_error) {
	puts("Divide Error");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_debug) {
	puts("Debug Interrupt");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_nmi) {
	puts("NMI Interrupt");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_breakpoint) {
	puts("Breakpoint");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_overflow) {
	puts("Overflow");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_bound) {
	puts("Bound range exceeded");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_invalid_opcode) {
	puts("Invalid Opcode");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_device) {
	puts("Device not available");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_double_fault) {
	puts("Double fault");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_invalid_tss) {
	puts("Invalid TSS");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_segment) {
	puts("Segment not present");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_stack) {
	puts("Stack exception");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_general_protection) {
	puts("General protection fault");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_page_fault) {
	puts("Page fault");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_fpu) {
	puts("Floating point exception");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_alignment) {
	puts("Alignment check");
	asm("cli");
	for(;;);
}
//
EXC_HANDLER(exc_machine_check) {
	puts("Machine-Check exception");
	asm("cli");
	for(;;);
}
//
void init_irq() {
	asm(
		"\n mov $0x11, %al" // Войти в режим программирования контроллера
		"\n out %al, $0x20"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n out %al, $0xa0"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0x20, %al" // Установить базовый вектор 0x20
		"\n out %al, $0x21"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0x28, %al" // Установить базовый вектор 0x28
		"\n out %al, $0xa1"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0x04, %al" // Выход из режима
		"\n out %al, $0x21"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0x02, %al" // Выход из режима
		"\n out %al, $0xa1"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0x01, %al" // Перегрузка контроллера
		"\n out %al, $0x21"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n out %al, $0xa1"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
		"\n mov $0xfe, %al" // Запретить все прерывания кроме таймера
		"\n out %al, $0x21"
		"\n jmp . + 2 \n jmp . + 2" // Задержка
	);
}
// Populate our interrupt descriptor table
void populate_idt(id_t * id) {
	set_id(id + 0, (dword)&exc_divide_error, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 1, (dword)&exc_debug, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 2, (dword)&exc_nmi, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 3, (dword)&exc_breakpoint, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 4, (dword)&exc_overflow, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 5, (dword)&exc_bound, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 6, (dword)&exc_invalid_opcode, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 7, (dword)&exc_device, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 8, (dword)&exc_double_fault, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 9, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 10, (dword)&exc_invalid_tss, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 11, (dword)&exc_segment, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 12, (dword)&exc_stack, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 13, (dword)&exc_general_protection, KERNEL_CS_SELECTOR, 0x8f);	
	set_id(id + 14, (dword)&exc_page_fault, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 15, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 16, (dword)&exc_fpu, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 17, (dword)&exc_alignment, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 18, (dword)&exc_machine_check, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 19, (dword)&exc_fpu, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 20, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 21, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 22, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 23, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 24, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 25, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 26, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 27, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 28, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 29, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 30, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	set_id(id + 31, (dword)&exc_reserved, KERNEL_CS_SELECTOR, 0x8f);
	// Timer
	set_id(id + 32, (dword)&irq_timer, KERNEL_CS_SELECTOR, 0x8e);
}

asm(
	"\n .globl irq_timer"
	"\n irq_timer:"
	// Save context
	"\n push %ds"
	"\n push %eax"
	"\n push %ebx"
	"\n push %ecx"
	"\n push %edx"
	"\n push %ebp"
	"\n push %esi"
	"\n push %edi"
	// Display !
	"\n push $0x21"
	"\n call putchar"
	"\n add $0x4, %esp"
	// Restore context
	"\n pop %edi"
	"\n pop %esi"
	"\n pop %ebp"
	"\n pop %edx"
	"\n pop %ecx"
	"\n pop %ebx"
	// Timer is handled
	"\n mov $0x20, %al"
	"\n out %al, $0x20"
	// Restore context (continue)
	"\n pop %eax"
	"\n pop %ds"
	// Return
	"\n iret"
);
