export async function fetchNews() {
  return [
    { id: 1, title: "Berita 1", date: "2025-12-01", summary: "Ringkasan berita" },
    { id: 2, title: "Berita 2", date: "2025-12-05", summary: "Ringkasan berita" },
  ];
}

export async function fetchNewsById(id: number) {
  return {
    id,
    title: `Berita ${id}`,
    content: "Ini isi lengkap beritanya.",
    date: "2025-12-01",
  };
}
