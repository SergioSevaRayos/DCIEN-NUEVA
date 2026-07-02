import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

const directoryPath = path.join(process.cwd(), 'public/images/brand');

async function convertToWebP() {
  try {
    const files = fs.readdirSync(directoryPath);
    for (const file of files) {
      const ext = path.extname(file).toLowerCase();
      if (['.png', '.jpg', '.jpeg'].includes(ext)) {
        const inputPath = path.join(directoryPath, file);
        const outputPath = path.join(directoryPath, `${path.parse(file).name}.webp`);
        
        await sharp(inputPath)
          .webp({ quality: 80 })
          .toFile(outputPath);
        
        console.log(`Converted: ${file} -> ${path.parse(file).name}.webp`);
        // Opcional: borrar el original
        // fs.unlinkSync(inputPath);
      }
    }
  } catch (err) {
    console.error('Error:', err);
  }
}

convertToWebP();
