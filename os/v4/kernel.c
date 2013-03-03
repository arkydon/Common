#include "ctype.h"
#include "kernel.h"
// GO
// PDBR
dword pdbr = 0x300000;
// MAX_MEM AND CHUNK
dword chunk;
dword memory_map;
mb_t * heap;
// GDT
gd_t gd[256];
gdtr_t gdtr;
// IDT
id_t id[256];
idtr_t idtr;
// Interface to FAT
int media_read(dword sector, dword count, byte * buffer) {
	read_pio(sector, buffer, 512 * count);
	return 1; // IF OK
	return 0; // IF NOT
}
// Kernel
void kernel(dword magic, dword addr) {
	dword * t;
	byte * buffer;
	multiboot_info_t * multiboot_info;
	// Print something
	clrscr();
	puts("Starting...\n");
	// MBoot check
	if(magic != MULTIBOOT_BOOTLOADER_MAGIC) {
		puts("Invalid magic number!\n");
		for(;;);
	}
	multiboot_info = (multiboot_info_t *)addr;
	if (CHECK_FLAG(multiboot_info -> flags, 4) && CHECK_FLAG(multiboot_info -> flags, 5)) {
        puts("Invalid! Both bits 4 and 5 are set!\n");
        for(;;);
	}
	printf("multiboot_info_flags = 0x%x\n", multiboot_info -> flags);
    if(CHECK_FLAG(multiboot_info -> flags, 0)) {
		printf("mem_lower = 0x%x KiB\n", multiboot_info -> mem_lower);
		printf("mem_upper = 0x%x KiB\n", multiboot_info -> mem_upper);
	}
	if(CHECK_FLAG(multiboot_info -> flags, 1)) {
		printf("boot_device = 0x%x\n", multiboot_info -> boot_device);
	}
	if(CHECK_FLAG(multiboot_info -> flags, 2)) {
		printf("cmdline = %s\n", (char *)multiboot_info -> cmdline);
	}
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
	// Init paging
	init_paging();
	asm ("" : :"a"(pdbr));
	asm("mov %eax, %cr3");
	asm("mov %cr0, %eax");
	asm("bts $0x1f, %eax");
	asm("mov %eax, %cr0");
	asm("jmp . + 2");
	// Test FAT
	fat_init(& media_read, 30);
	buffer = malloc(512);
    fat_loadfile("test", buffer, 512);
	puts((char *)buffer);
	// Permit int
	asm("sti");
	// Test paging
	t = (dword *)0x900000;
	*t = 7;
	// LOOP
	for(;;);
}
// Paging
void set_pde(pde_t * pde, dword addr, dword mask) {
	addr = addr & 0xfffff000;
	addr = addr | mask;
	* pde = addr;
}
void set_pte(pte_t * pte, dword addr, dword mask) {
	addr = addr & 0xfffff000;
	addr = addr | mask;
	* pte = addr;
}
EXC_HANDLER(exc_page_fault) {
	dword i;
	dword cr2;
	byte * cell;
	asm("cli");
	asm("mov %%cr2, %%eax" :"=a"(cr2) : );
	printf("Page fault at 0x%x\n", cr2);
	for(i = 0; i < chunk; i++) {
		cell = (byte *)memory_map + i;
		if(*cell) ; else {
			*cell = 1;
			set_pte((pte_t *)(pdbr + 4096 + (cr2 >> 12) * 4), i * 4096, PTE_PBIT | PTE_RWBIT);
			asm("mov %cr3, %eax");
			asm("mov %eax, %cr3");
			asm("jmp . + 2");
			asm("sti");
			return;
		}
	}
	puts("Out of free memory!");
	for(;;);
}
void init_paging() {
    dword i;
    dword mask;
	dword max_mem;
    byte * cell;
    max_mem = get_max_mem();
    chunk = max_mem / (4 * 1024);
    printf("max_mem = 0x%x\n", max_mem);
    for(i = 0; i < 1024; i++) {
        set_pde((pde_t *)(pdbr + i * 4), pdbr + 4096 + i * 4096, PDE_PBIT | PDE_RWBIT);
    }
    memory_map = pdbr + 4096 + 1024 * 4096;
    for(i = 0; i < 1024 * 1024; i++) {
        mask = i * 4096 < memory_map + 1024 * 1024  ? PTE_PBIT | PTE_RWBIT : 0x0;
        cell = (byte *)memory_map + i;
        *cell = mask ? 1 : 0;
        set_pte((pte_t *)(pdbr + 4096 + i * 4), i * 4096, mask);
    }
    heap = (mb_t *)(memory_map + 1024 * 1024);
    heap -> link = (mb_t *)0;
    heap -> size = 0x100000;
}
dword get_max_mem() {
	dword pos;
	dword check;
	dword magic = 0x123456;
	for(pos = 0x100000 ; ; pos += 4096) {
		check = 0;
		asm("" : :"S"(pos));
		asm("" : :"a"(magic));
		asm("xchg %eax, %es:(%esi)");
		asm("xchg %es:(%esi), %eax");
		asm("" :"=a"(check) : );
		if(check != magic) return pos;
	}
}
// MALLOC
void * malloc(size_t size) {
	dword p;
	mb_t * mb;
	size += sizeof(mb_t);
	mb = heap;
	while(mb -> link) mb = mb -> link;
	if(mb -> size < size) return (void *)0;
	mb -> link = (mb_t *)((dword)mb + size);
	size = mb -> size - size;
	p = (dword)mb + sizeof(mb_t);
	mb = mb -> link;
	mb -> link = (mb_t *)0;
	mb -> size = size;
	return (void *)p;
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
// Output double word to port
void outdw(word port, dword value) {
	asm("outl %0, %w1":: "a"(value), "d"(port));
}
// Input double word from port
dword indw(word port) {
	dword value;
	asm("inl %w1, %0": "=a"(value): "d"(port));
	return value;
}
// Output byte to port
void outb(word port, byte value) {
	asm("outb %0, %w1":: "a"(value), "d"(port));
}
// Input byte from port
byte inb(word port) {
	byte value;
	asm("inb %w1, %0": "=a"(value): "d"(port));
	return value;
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
// Timer
void irq_timer(void);
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
