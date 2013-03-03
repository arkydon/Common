#ifndef _CTYPE_H
#define _CTYPE_H

#define NULL 0
#define PDE_PBIT  0x1
#define PTE_PBIT  0x1
#define PDE_RWBIT 0x2
#define PTE_RWBIT 0x2
#define NUMBER_OF_TASKS	64
#define TASK_NAME_SIZE	64
#define MULTIBOOT_BOOTLOADER_MAGIC 0x2badb002
#define MULTIBOOT_MEMORY_AVAILABLE 1
#define MULTIBOOT_MEMORY_RESERVED  2
#define CHECK_FLAG(flags, bit) ((flags) & (1 << (bit)))

typedef unsigned char byte;
typedef unsigned short int word;
typedef unsigned long int dword;
typedef unsigned long long int qword;
typedef dword size_t;
typedef dword pde_t;
typedef dword pte_t;
typedef word multiboot_uint16_t;
typedef dword multiboot_uint32_t;
typedef qword multiboot_uint64_t;
//typedef size_t uintptr_t;
//typedef dword uint32;
//typedef word uint16;
//typedef byte uint8;


//#define assert(ignore)((void) 0)
//#define islower(x) ((x) >= 'a' && (x) <= 'z')
//#define isupper(x) ((x) >= 'A' && (x) <= 'Z')
//#define tolower(c) (isupper(c) ? ((c) - 'A' + 'a') : (c))
//#define toupper(c) (islower(c) ? ((c) - 'a' + 'A') : (c))

typedef struct {
	word offset_low;
	word selector;
	byte reserved;
	byte type;
	word offset_high;
} __attribute__((__packed__)) id_t;

typedef struct {
	word limit_low;
	word base_low;
	byte base_mid;
	byte type;
	byte limit_high;
	byte base_high;
} __attribute__((__packed__)) gd_t;

typedef struct {
	word limit;
	gd_t * gd;
} __attribute__((__packed__)) gdtr_t;

typedef struct {
	word limit;
	id_t * id;
} __attribute__((__packed__)) idtr_t;

typedef struct {
	dword ss;
	dword esp;
	dword pid;
	dword ppid;
	dword state;
	char name[TASK_NAME_SIZE];
} __attribute__((__packed__)) task_t;

typedef struct {
	dword count;
	dword current;
	task_t task[NUMBER_OF_TASKS];
} __attribute__((__packed__)) task_list_t;

typedef struct {
	size_t size;
	void * link;
} __attribute__((__packed__)) mb_t;

typedef struct {
	multiboot_uint32_t size;
	multiboot_uint64_t addr;
	multiboot_uint64_t len;
	multiboot_uint32_t type;
} __attribute__((__packed__)) multiboot_memory_map_t;

typedef struct {
	multiboot_uint32_t tabsize;
	multiboot_uint32_t strsize;
	multiboot_uint32_t addr;
	multiboot_uint32_t reserved;
} __attribute__((__packed__)) multiboot_aout_symbol_table_t;

typedef struct {
	multiboot_uint32_t num;
	multiboot_uint32_t size;
	multiboot_uint32_t addr;
	multiboot_uint32_t shndx;
} __attribute__((__packed__)) multiboot_elf_section_header_table_t;

typedef struct {
	/* Multiboot info version number */
	multiboot_uint32_t flags;
     
	/* Available memory from BIOS */
	multiboot_uint32_t mem_lower;
	multiboot_uint32_t mem_upper;
     
	/* "root" partition */
	multiboot_uint32_t boot_device;
     
	/* Kernel command line */
	multiboot_uint32_t cmdline;
     
	/* Boot-Module list */
	multiboot_uint32_t mods_count;
	multiboot_uint32_t mods_addr;
     
	union {
		multiboot_aout_symbol_table_t aout_sym;
		multiboot_elf_section_header_table_t elf_sec;
	} u;
     
	/* Memory Mapping buffer */
	multiboot_uint32_t mmap_length;
	multiboot_uint32_t mmap_addr;
     
	/* Drive Info buffer */
	multiboot_uint32_t drives_length;
	multiboot_uint32_t drives_addr;
     
	/* ROM configuration table */
	multiboot_uint32_t config_table;
     
	/* Boot Loader Name */
	multiboot_uint32_t boot_loader_name;
     
	/* APM table */
	multiboot_uint32_t apm_table;
     
	/* Video */
	multiboot_uint32_t vbe_control_info;
	multiboot_uint32_t vbe_mode_info;
	multiboot_uint16_t vbe_mode;
	multiboot_uint16_t vbe_interface_seg;
	multiboot_uint16_t vbe_interface_off;
	multiboot_uint16_t vbe_interface_len;
} __attribute__((__packed__)) multiboot_info_t;

typedef int (* read_callback)(dword, dword, byte *);

#endif
