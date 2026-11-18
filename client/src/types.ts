export interface User {
  id: number;
  name: string;
  email: string;
}

export interface Video {
  id: string;
  title: string;
  description: string | null;
  createdAt: string;
  updatedAt: string;
  videoUrl: string;
  shareUrl: string;
}
